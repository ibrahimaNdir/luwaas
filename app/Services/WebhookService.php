<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BailService;
use App\Services\PaymentService;
use App\Services\CommissionService;

class WebhookService
{
    public function __construct(
        protected BailService              $bailService,
        protected PaymentService           $paymentService,
        protected PaymentGatewayInterface  $gateway,
        protected CommissionService        $commissionService
    ) {}

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // HANDLER PRINCIPAL
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    public function handle(Request $request): array
    {
        try {
            // ── Vérification signature (délégué au gateway)
            if (!$this->gateway->verifyWebhook($request)) {
                Log::warning('��❌ Signature webhook invalide', ['ip' => $request->ip()]);
                return ['error' => 'Invalid signature', 'status' => 403];
            }

            // ── Normalisation payload (indépendant du gateway)
            $payload = $this->gateway->normalizeWebhookPayload($request);

            $token          = $payload['token'];
            $statut         = $payload['status'];
            $montantRecu    = $payload['amount'] > 0 ? $payload['amount'] : null;
            $transactionRef = $payload['transaction_ref'];

            if (!$token) {
                Log::error('��❌ Token manquant dans le webhook', ['payload' => $request->all()]);
                return ['error' => 'Missing token', 'status' => 400];
            }

            if (!$statut) {
                Log::error('��❌ Statut manquant dans le webhook', ['token' => $token]);
                return ['error' => 'Missing status', 'status' => 400];
            }

            // ── Retrouver la transaction locale via le token gateway
            $transaction = Transaction::query()
                ->where('gateway_token', $token)
                ->with([
                    'paiement.bail.logement.propriete.proprietaire',
                    'subscription.plan',
                    'subscription.proprietaire',
                ])
                ->first();

            if (!$transaction) {
                Log::error("��❌ Transaction introuvable pour token {$token}");
                return ['error' => 'Transaction not found', 'status' => 404];
            }

            // ── Idempotence : déjà traitée ?
            if ($transaction->statut !== 'en_attente') {
                Log::info("��⏭��️ Transaction {$transaction->id} déjà traitée", ['statut' => $transaction->statut]);
                return ['success' => true, 'message' => 'Already processed', 'status' => 200];
            }

            // ── Vérification du montant si fourni
            if ($montantRecu !== null && !$this->montantsCorrespondent($transaction->montant, $montantRecu)) {
                Log::error("��❌ Montant incorrect pour transaction {$transaction->id}", [
                    'attendu' => $transaction->montant,
                    'recu'    => $montantRecu,
                ]);
                return ['error' => 'Invalid amount', 'status' => 400];
            }

            // ── Router vers le handler approprié
            return match ($transaction->type) {
                'rent_payment'         => $this->handlePayment($transaction, $statut),
                'subscription_payment' => $this->handleSubscription($transaction, $statut, $transactionRef),
                default                => ['error' => 'Unknown transaction type', 'status' => 400],
            };

        } catch (\Throwable $e) {
            Log::error('��❌ Erreur inattendue dans WebhookService', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return ['error' => 'Internal server error', 'status' => 500];
        }
    }

    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═��
    // HANDLER LOYER
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��

    private function handlePayment(Transaction $transaction, string $statut): array
    {
        $paiement = $transaction->paiement;

        if (!$paiement) {
            Log::error("��❌ Paiement introuvable pour transaction {$transaction->id}");
            return ['error' => 'Paiement not found', 'status' => 404];
        }

        switch ($statut) {
            case 'completed':
                DB::transaction(function () use ($transaction, $paiement) {
                    $transaction->update([
                        'statut'           => 'valide',
                        'date_transaction' => $transaction->date_transaction ?? now(),
                    ]);

                    // ── Validation du paiement principal
                    $paiement->update([
                        'statut'          => 'payé',
                        'montant_paye'    => $paiement->montant_attendu,
                        'montant_restant' => 0,
                    ]);

                    // ── Gestion du paiement de plusieurs mois d'avance (ex: 2 mois d'un coup)
                    $montantPayeTotal = (float) $transaction->montant;
                    $reliquat = $montantPayeTotal - (float) $paiement->montant_attendu;

                    if ($reliquat > 0 && $paiement->bail_id) {
                        $prochainsPaiements = \App\Models\Paiement::where('bail_id', $paiement->bail_id)
                            ->where('id', '!=', $paiement->id)
                            ->where('statut', '!=', 'payé')
                            ->orderBy('date_echeance', 'asc')
                            ->get();

                        foreach ($prochainsPaiements as $prochain) {
                            if ($reliquat >= (float) $prochain->montant_attendu) {
                                $prochain->update([
                                    'statut'          => 'payé',
                                    'montant_paye'    => $prochain->montant_attendu,
                                    'montant_restant' => 0,
                                ]);
                                $reliquat -= (float) $prochain->montant_attendu;
                            } else {
                                break;
                            }
                        }
                    }

                    $bail = $paiement->bail;

                    if (!$bail) {
                        throw new \RuntimeException("Bail introuvable pour paiement {$paiement->id}");
                    }

                    // ── Activation du bail à la signature
                    if ($paiement->type === 'signature') {
                        $bail->update([
                            'statut'          => 'actif',
                            'date_activation' => now(),
                        ]);
                        $bail->logement->update(['statut_occupe' => 'occupe']);
                        event(new \App\Events\BailSigne($bail));
                        $this->bailService->genererLoyersMensuels($bail);
                        $this->bailService->genererEtStockerPdf($bail);
                    }

                    // ── Mise à jour du score de fiabilité du locataire (toujours, dès qu'un paiement en ligne est validé)
                    $bailleur  = $bail->logement->propriete->proprietaire ?? null;
                    $locataire = $bail->locataire ?? null;

                    if ($locataire) {
                        try {
                            app(\App\Services\ScoreLocataireService::class)->mettreAJourScore($locataire, $paiement);
                        } catch (\Exception $e) {
                            Log::error('��⚠��️ Mise à jour score locataire échouée (loyer quand même validé)', [
                                'paiement_id'  => $paiement->id,
                                'locataire_id' => $locataire->id,
                                'erreur'       => $e->getMessage(),
                            ]);
                        }
                    }

                    // ── Reversement au bailleur (montant NET = loyer - frais Luwaas)
                    if ($bailleur && $bailleur->payout_phone) {
                        try {
                            // Envoyer la notification
                            app(\App\Services\NotificationService::class)->sendPaymentReceivedNotification($bail, $paiement);

                            // Distribuer les fonds au bailleur
                            $this->paymentService->redistributeToBailleur(
                                $bailleur,
                                (float) $transaction->montant,
                                $transaction
                            );
                        } catch (\Exception $e) {
                            Log::error('��⚠��️ Reversement bailleur échoué (loyer quand même validé)', [
                                'paiement_id'    => $paiement->id,
                                'transaction_id' => $transaction->id,
                                'erreur'         => $e->getMessage(),
                            ]);
                        }
                    }
                });

                Log::info('��✅ Paiement loyer validé', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement validé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                $transaction->update(['statut' => 'rejete']);
                Log::warning('��⚠��️ Paiement loyer rejeté', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('��⏳ Paiement loyer en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("��⚠��️ Statut inconnu pour paiement loyer : {$statut}", ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
        }
    }

    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═�═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
    // HANDLER ABONNEMENT
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��

    private function handleSubscription(Transaction $transaction, string $statut, string $transactionRef): array
    {
        $subscription = $transaction->subscription;

        if (!$subscription)              return ['error' => 'Subscription not found', 'status' => 404];
        if (!$subscription->plan)        return ['error' => 'Plan not found', 'status' => 404];
        if (!$subscription->proprietaire) return ['error' => 'Proprietaire not found', 'status' => 404];

        switch ($statut) {
            case 'completed':
                $startedAt = now();
                $endsAt    = $subscription->plan->billing_cycle === 'yearly'
                    ? $startedAt->copy()->addYear()
                    : $startedAt->copy()->addMonth();

                DB::transaction(function () use ($transaction, $subscription, $transactionRef, $startedAt, $endsAt) {
                    $transaction->update(['statut' => 'valide']);

                    $subscription->update([
                        'status'          => 'active',
                        'transaction_ref' => $transactionRef,
                        'starts_at'       => $startedAt,
                        'ends_at'         => $endsAt,
                    ]);

                    $subscription->proprietaire->update([
                        'subscription_status'  => 'active',
                        'plan'                 => $subscription->plan->tier,
                        'billing_cycle'        => $subscription->plan->billing_cycle,
                        'subscription_ends_at' => $endsAt,
                    ]);
                });

                Log::info('��✅ Abonnement activé', [
                    'subscription_id' => $subscription->id,
                    'plan'            => $subscription->plan->tier,
                ]);
                return ['success' => true, 'message' => 'Abonnement activé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                DB::transaction(function () use ($transaction, $subscription) {
                    $transaction->update(['statut' => 'rejete']);
                    $subscription->update(['status' => 'failed']);
                });
                Log::warning('��⚠��️ Paiement abonnement annulé', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('��⏳ Paiement abonnement en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("��⚠��️ Statut inconnu pour abonnement : {$statut}", ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
            }
        }

    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
    // HELPERS
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��

    private function montantsCorrespondent(float $attendu, float $recu): bool
    {
        return number_format($attendu, 2, '.', '') === number_format($recu, 2, '.', '');
    }
}
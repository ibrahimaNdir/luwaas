<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use App\Contracts\PaymentGatewayInterface;
use App\Constants\PaymentStatus;

class WebhookService
{
    protected string $gatewayKey;
    protected GatewayResolver $gatewayResolver;

    public function __construct(
        protected BailService $bailService,
        protected LandlordEarningsService $landlordEarningsService,
        protected PayoutProcessorService $payoutProcessorService,
        GatewayResolver $gatewayResolver, string $gatewayKey = 'paydunya'
    ) {
        $this->gatewayKey = $gatewayKey;
        $this->gatewayResolver = $gatewayResolver;
    }

    // ═════════════════════════════════════════════
    // HANDLER PRINCIPAL
    // ═════════════════════════════════════════════

    public function handle(Request $request): array
    {
        try {
            // Get the specific gateway implementation based on the configured key
            $gateway = $this->gatewayResolver->resolve($this->gatewayKey);

            // 1. Vérifier la signature via le gateway
            if (!$gateway->verifyWebhook($request)) {
                Log::warning('❌ Signature webhook invalide', ['ip' => $request->ip()]);
                return ['error' => 'Invalid signature', 'status' => 403];
            }

            // 2. Normaliser le payload via le gateway
            $payload = $gateway->normalizeWebhookPayload($request);
            $token = $payload['token'];
            $statut = $payload['status'];
            $montantRecu = $this->normaliserMontant($payload['amount']);
            $transactionRef = $payload['transaction_ref'];

            if (!$token) {
                Log::error('❌ Token manquant dans webhook', ['payload' => $request->all()]);
                return ['error' => 'Missing token', 'status' => 400];
            }

            if (!$statut) {
                Log::error('❌ Statut manquant dans webhook', [
                    'token' => $token,
                    'payload' => $request->all(),
                ]);
                return ['error' => 'Missing status', 'status' => 400];
            }

            // 3. Trouver la transaction associée
            $transaction = Transaction::query()
                ->where('gateway_token', $token)
                ->with([
                    'paiement.bail.logement.propriete.proprietaire',
                    'subscription.plan',
                    'subscription.proprietaire',
                ])
                ->first();

            if (!$transaction) {
                Log::error("❌ Transaction introuvable pour le token {$token}");
                return ['error' => 'Transaction introuvable', 'status' => 404];
            }

            // 4. Idempotence : déjà traitée ?
            if ($transaction->statut !== PaymentStatus::PENDING) {
                Log::info("⏭️ Transaction {$transaction->id} déjà traitée", ['statut' => $transaction->statut]);
                return ['success' => true, 'message' => 'Already processed', 'status' => 200];
            }

            // 5. Validation critique : le gateway de la transaction correspond bien au gateway configuré
            if ($transaction->gateway_used !== $this->gatewayKey) {
                Log::error("❌ Mismatch de gateway pour transaction {$transaction->id}", [
                    'transaction_gateway_used' => $transaction->gateway_used,
                    'token' => $token,
                ]);
                return ['error' => 'Gateway mismatch', 'status' => 400];
            }

            // 6. Vérifier le montant si présent
            if ($montantRecu !== null && !$this->montantsCorrespondent($transaction->montant, $montantRecu)) {
                Log::error("❌ Montant incorrect pour la transaction {$transaction->id}", [
                    'attendu' => $this->normaliserMontant($transaction->montant),
                    'recu' => $montantRecu,
                    'token' => $token,
                ]);
                return ['error' => 'Invalid amount', 'status' => 400];
            }

            // 7. Traiter selon le type de transaction
            return match ($transaction->type) {
                'rent_payment' => $this->handlePayment($transaction, $statut),
                'subscription_payment' => $this->handleSubscription($transaction, $statut, $token),
                default => ['error' => 'Unknown transaction type', 'status' => 400],
            };
        } catch (\Throwable $e) {
            Log::error('❌ Erreur inattendue dans WebhookService', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['error' => 'Internal server error', 'status' => 500];
        }
    }

    // ═════════════════════════════════════════════
    // HANDLER LOYER
    // ═════════════════════════════════════════════

    private function handlePayment(Transaction $transaction, string $statut): array
    {
        $paiement = $transaction->paiement;

        if (!$paiement) {
            Log::error("❌ Paiement introuvable pour transaction {$transaction->id}");
            return ['error' => 'Paiement introuvable', 'status' => 404];
        }

        switch ($statut) {
            case 'completed':
                DB::transaction(function () use ($transaction, $paiement) {
                    $transaction->update([
                        'statut' => PaymentStatus::VALID,
                        'date_transaction' => $transaction->date_transaction ?? now(),
                    ]);

                    $paiement->update([
                        'statut' => PaymentStatus::PAYMENT_PAID,
                        'montant_paye' => $paiement->montant_attendu,
                        'montant_restant' => 0,
                    ]);

                    // Gestion du paiement de plusieurs mois d'avance
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
                                    'statut' => PaymentStatus::PAYMENT_PAID,
                                    'montant_paye' => $prochain->montant_attendu,
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

                    if ($paiement->type === 'signature') {
                        $bail->update([
                            'statut' => PaymentStatus::BAIL_ACTIVE,
                            'date_activation' => now(),
                        ]);
                        $bail->logement->update(['statut_occupe' => 'occupe']);

                        event(new \App\Events\BailSigne($bail));

                        try {
                            $this->bailService->genererLoyersMensuels($bail);
                        } catch (\Exception $e) {
                            Log::error('⚠️ Génération des loyers mensuels échouée (bail quand même activé)', [
                                'bail_id' => $bail->id,
                                'erreur' => $e->getMessage(),
                            ]);
                        }

                        try {
                            $this->bailService->genererEtStockerPdf($bail);
                        } catch (\Exception $e) {
                            Log::error('⚠️ Génération et stockage du PDF échoué (bail quand même activé)', [
                                'bail_id' => $bail->id,
                                'erreur' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Mise à jour du score de fiabilité du locataire
                    
                   /* $locataire = $bail->locataire ?? null;

                    if ($locataire) {
                        try {
                            app(\App\Services\ScoreLocataireService::class)->mettreAJourScore($locataire, $paiement);
                        } catch (\Exception $e) {
                            Log::error('⚠️ Mise à jour score locataire échouée (loyer quand même validé)', [
                                'paiement_id' => $paiement->id,
                                'locataire_id' => $locataire->id,
                                'erreur' => $e->getMessage(),
                            ]);
                        }
                    } */
                    

                    // Notification + traitement du versement au bailleur via le système asynchrone
                    try {
                        app(\App\Services\NotificationService::class)->sendPaymentReceivedNotification($bail, $paiement);

                        $this->landlordEarningsService->triggerPayoutCalculation($paiement);

                        $paymentDate = $paiement->date_paiement ?? now();
                        $periodStart = $paymentDate->copy()->startOfMonth();
                        $periodEnd = $paymentDate->copy()->endOfMonth();

                        $payoutToProcess = $bail->logement->propriete->proprietaire->payouts()
                            ->where('period_start', $periodStart->toDateString())
                            ->where('period_end', $periodEnd->toDateString())
                            ->whereIn('status', ['pending', 'processing'])
                            ->first();

                        if ($payoutToProcess) {
                            $result = $this->payoutProcessorService->processPayout($payoutToProcess);

                            if (!$result['success']) {
                                Log::warning("Échec du versement après paiement {$paiement->id}", [
                                    'paiement_id' => $paiement->id,
                                    'payout_id' => $payoutToProcess->id,
                                    'error' => $result['message'],
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Impossible de traiter le versement après paiement {$paiement->id}", [
                            'paiement_id' => $paiement->id,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                });

                Log::info('✅ Paiement loyer validé', [
                    'transaction_id' => $transaction->id,
                    'paiement_id' => $paiement->id,
                ]);

                return ['success' => true, 'message' => 'Paiement validé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                $transaction->update(['statut' => PaymentStatus::FAILED]);
                Log::warning('⚠️ Paiement loyer rejeté ou annulé', [
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement loyer en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement en attente', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour paiement loyer : {$statut}", [
                    'transaction_id' => $transaction->id,
                ]);
                return ['success' => true, 'message' => 'Statut inconnu', 'status' => 200];
        }
    }

    // ═════════════════════════════════════════════
    // HANDLER ABONNEMENT
    // ═════════════════════════════════════════════

    private function handleSubscription(Transaction $transaction, string $statut, string $transactionRef): array
    {
        $subscription = $transaction->subscription;

        if (!$subscription) {
            Log::error("❌ Subscription introuvable pour transaction {$transaction->id}");
            return ['error' => 'Subscription introuvable', 'status' => 404];
        }

        if (!$subscription->plan) {
            Log::error("❌ Plan introuvable pour subscription {$subscription->id}");
            return ['error' => 'Plan introuvable', 'status' => 404];
        }

        if (!$subscription->proprietaire) {
            Log::error("❌ Propriétaire introuvable pour subscription {$subscription->id}");
            return ['error' => 'Proprietaire introuvable', 'status' => 404];
        }

        switch ($statut) {
            case 'completed':
                $startedAt = now();
                $endsAt = $subscription->plan->billing_cycle === 'yearly'
                    ? $startedAt->copy()->addYear()
                    : $startedAt->copy()->addMonth();

                DB::transaction(function () use ($transaction, $subscription, $transactionRef, $startedAt, $endsAt) {
                    $transaction->update(['statut' => PaymentStatus::VALID]);

                    $subscription->update([
                        'status' => PaymentStatus::SUBSCRIPTION_ACTIVE,
                        'transaction_ref' => $transactionRef,
                        'starts_at' => $startedAt,
                        'ends_at' => $endsAt,
                    ]);

                    $subscription->proprietaire->update([
                        'subscription_status' => PaymentStatus::SUBSCRIPTION_ACTIVE,
                        'plan' => $subscription->plan->tier,
                        'billing_cycle' => $subscription->plan->billing_cycle,
                        'subscription_ends_at' => $endsAt,
                    ]);
                });

                Log::info('✅ Abonnement activé', [
                    'transaction_id' => $transaction->id,
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan->tier,
                ]);

                return ['success' => true, 'message' => 'Abonnement activé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                DB::transaction(function () use ($transaction, $subscription) {
                    $transaction->update(['statut' => PaymentStatus::FAILED]);
                    $subscription->update(['status' => PaymentStatus::SUBSCRIPTION_FAILED]);

                    # Update associated paiement if it exists (similar to handlePayment)
                    if ($subscription->paiement) {
                        $subscription->paiement->update([
                            'statut' => PaymentStatus::PAYMENT_FAILED,
                        ]);
                    }
                });
                Log::warning('⚠️ Paiement abonnement annulé ou échoué', [
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement abonnement en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement en attente', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour abonnement : {$statut}", [
                    'transaction_id' => $transaction->id,
                ]);
                return ['success' => true, 'message' => 'Statut inconnu', 'status' => 200];
        }
    }

    // ═════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════

    private function normaliserMontant(?string $montant): ?string
    {
        if ($montant === null) {
            return null;
        }

        return number_format((float) $montant, 2, '.', '');
    }

    private function montantsCorrespondent(string $montantBase, string $montantRecu): bool
    {
        return $this->normaliserMontant($montantBase) === $this->normaliserMontant($montantRecu);
    }
}
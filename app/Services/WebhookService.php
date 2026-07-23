<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BailService;

class WebhookService
{
    public function __construct(protected BailService $bailService) {}
    // ═══════════════════════════════════════════
    // VÉRIFICATION SIGNATURE
    // ═══════════════════════════════════════════

    public function verifierSignature(Request $request): bool
    {
        $masterKey = config('services.paydunya.master_key');

        if (!$masterKey) {
            Log::error('Clé PayDunya manquante dans la configuration.');
            return false;
        }

        $expectedHash = hash('sha512', $masterKey);
        $headerHash = $request->header('PAYDUNYA-MASTER-KEY');
        $payloadHash = $request->input('data.hash');

        if ($headerHash && hash_equals($expectedHash, $headerHash)) {
            return true;
        }

        if ($payloadHash && hash_equals($expectedHash, $payloadHash)) {
            return true;
        }

        return false;
    }

    // ═══════════════════════════════════════════
    // HANDLER PRINCIPAL
    // ═══════════════════════════════════════════

    public function handle(Request $request): array
    {
        try {
            if (!$this->verifierSignature($request)) {
                Log::warning('❌ Signature webhook PayDunya invalide', [
                    'ip' => $request->ip(),
                ]);

                return ['error' => 'Invalid signature', 'status' => 403];
            }

            $token = $request->input('data.invoice.token')
                ?? $request->input('data.token')
                ?? $request->input('token');

            $statut = $request->input('data.status')
                ?? $request->input('status');

            $montantRecu = $this->normaliserMontant(
                $request->input('data.invoice.total_amount')
                    ?? $request->input('data.total_amount')
                    ?? $request->input('total_amount')
            );

            $transactionRef = $request->input('data.transaction_id')
                ?? $request->input('transaction_id')
                ?? $token;

            if (!$token) {
                Log::error('❌ Token manquant dans IPN PayDunya', [
                    'payload' => $request->all(),
                ]);

                return ['error' => 'Missing token', 'status' => 400];
            }

            if (!$statut) {
                Log::error('❌ Statut manquant dans IPN PayDunya', [
                    'token' => $token,
                    'payload' => $request->all(),
                ]);

                return ['error' => 'Missing status', 'status' => 400];
            }

            $transaction = Transaction::query()
                ->where('paydunyatoken', $token)
                ->with([
                    'paiement.bail',
                    'subscription.plan',
                    'subscription.proprietaire',
                ])
                ->first();

            if (!$transaction) {
                Log::error("❌ Transaction introuvable pour le token {$token}");

                return ['error' => 'Transaction not found', 'status' => 404];
            }

            if ($transaction->statut !== 'en_attente') {
                Log::info("⏭️ Transaction {$transaction->id} déjà traitée", [
                    'transaction_id' => $transaction->id,
                    'statut' => $transaction->statut,
                ]);

                return ['success' => true, 'message' => 'Already processed', 'status' => 200];
            }

            if ($montantRecu !== null && !$this->montantsCorrespondent($transaction->montant, $montantRecu)) {
                Log::error("❌ Montant incorrect pour la transaction {$transaction->id}", [
                    'attendu' => $this->normaliserMontant($transaction->montant),
                    'recu' => $montantRecu,
                    'token' => $token,
                ]);

                return ['error' => 'Invalid amount', 'status' => 400];
            }

            return match ($transaction->type) {
                'rent_payment' => $this->handlePayment($transaction, $statut),
                'subscription_payment' => $this->handleSubscription($transaction, $statut, $transactionRef),
                default => [
                    'error' => 'Unknown transaction type',
                    'status' => 400,
                ],
            };
        } catch (\Throwable $e) {
            Log::error('❌ Erreur inattendue dans WebhookService', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => 'Internal server error', 'status' => 500];
        }
    }

    // ═══════════════════════════════════════════
    // HANDLER LOYER
    // ═══════════════════════════════════════════

    private function handlePayment(Transaction $transaction, string $statut): array
    {
        $paiement = $transaction->paiement;

        if (!$paiement) {
            Log::error("❌ Paiement introuvable pour transaction {$transaction->id}");

            return ['error' => 'Paiement not found', 'status' => 404];
        }

        switch ($statut) {
            case 'completed':
                DB::transaction(function () use ($transaction, $paiement) {
                    $transaction->update([
                        'statut' => 'valide',
                        'date_transaction' => $transaction->date_transaction ?? now(),
                    ]);

                    $paiement->update([


                        'statut'          => 'payé',
                        'montant_paye'    => $transaction->montant,
                        'montant_restant' => max(0, $paiement->montant_attendu - $transaction->montant),
                        'date_paiement'   => now(),
                    ]);

                    if ($paiement->type === 'signature') {
                        $bail = $paiement->bail;

                        if (!$bail) {
                            throw new \RuntimeException("Bail introuvable pour paiement {$paiement->id}");
                        }

                        $bail->update([
                            'statut' => 'actif',
                            'date_activation' => now(),
                        ]);

                        $bail->logement->update(['statut_occupe' => 'occupe']);

                        event(new \App\Events\BailSigne($bail)); 

                        $this->bailService->genererLoyersMensuels($bail);
                         $this->bailService->genererEtStockerPdf($bail); 
                    }
                });

                Log::info('✅ Paiement loyer validé', [
                    'transaction_id' => $transaction->id,
                    'paiement_id' => $paiement->id,
                ]);

                return ['success' => true, 'message' => 'Paiement validé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                $transaction->update(['statut' => 'rejete']);

                Log::warning('⚠️ Paiement loyer rejeté ou annulé', [
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);

                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement loyer en attente', [
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour paiement loyer : {$statut}", [
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
        }
    }

    // ═══════════════════════════════════════════
    // HANDLER ABONNEMENT
    // ═══════════════════════════════════════════

    private function handleSubscription(Transaction $transaction, string $statut, string $transactionRef): array
    {
        $subscription = $transaction->subscription;

        if (!$subscription) {
            Log::error("❌ Subscription introuvable pour transaction {$transaction->id}");

            return ['error' => 'Subscription not found', 'status' => 404];
        }

        if (!$subscription->plan) {
            Log::error("❌ Plan introuvable pour subscription {$subscription->id}");

            return ['error' => 'Plan not found', 'status' => 404];
        }

        if (!$subscription->proprietaire) {
            Log::error("❌ Propriétaire introuvable pour subscription {$subscription->id}");

            return ['error' => 'Proprietaire not found', 'status' => 404];
        }

        switch ($statut) {
            case 'completed':
                $startedAt = now();
                $endsAt = $subscription->plan->billing_cycle === 'yearly'
                    ? $startedAt->copy()->addYear()
                    : $startedAt->copy()->addMonth();

                DB::transaction(function () use ($transaction, $subscription, $transactionRef, $startedAt, $endsAt) {
                    $transaction->update([
                        'statut' => 'valide',
                    ]);

                    $subscription->update([
                        'status' => 'active',
                        'transaction_ref' => $transactionRef,
                        'starts_at' => $startedAt,
                        'ends_at' => $endsAt,
                    ]);

                    $subscription->proprietaire->update([
                        'subscription_status' => 'active',
                        'plan' => $subscription->plan->tier,
                        'billing_cycle' => $subscription->plan->billing_cycle,
                        'subscription_ends_at' => $endsAt,
                    ]);
                });

                Log::info('✅ Abonnement activé', [
                    'transaction_id' => $transaction->id,
                    'subscription_id' => $subscription->id,
                    'proprietaire_id' => $subscription->proprietaire_id,
                    'plan' => $subscription->plan->tier,
                ]);

                return ['success' => true, 'message' => 'Abonnement activé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                DB::transaction(function () use ($transaction, $subscription) {
                    $transaction->update(['statut' => 'rejete']);
                    $subscription->update(['status' => 'failed']);
                });

                Log::warning('⚠️ Paiement abonnement annulé ou échoué', [
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);

                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement abonnement en attente', [
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour abonnement : {$statut}", [
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
        }
    }

    // ═══════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════

    private function normaliserMontant($montant): ?string
    {
        if ($montant === null || $montant === '') {
            return null;
        }

        return number_format((float) $montant, 2, '.', '');
    }

    private function montantsCorrespondent($montantBase, $montantRecu): bool
    {
        return $this->normaliserMontant($montantBase) === $this->normaliserMontant($montantRecu);
    }
}

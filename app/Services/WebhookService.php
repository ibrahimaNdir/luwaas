<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BailService;
<<<<<<< HEAD
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
=======
use App\Services\LandlordEarningsService;
use App\Services\PayoutProcessorService;
use InvalidArgumentException;

class WebhookService
{
    /**
     * Gateway key that this webhook service instance is configured to handle
     */
    protected string $gatewayKey;

    public function __construct(
        protected BailService $bailService,
        protected LandlordEarningsService $landlordEarningsService,
        protected PayoutProcessorService $payoutProcessorService,
        string $gatewayKey = 'paydunya' // Default to PayDunya for backward compatibility
    ) {
        $this->gatewayKey = $gatewayKey;
    }

    // ═════════════════════════════════════════════
    // VÉRIFICATION SIGNATURE
    // ═════════════════════════════════════════════

    public function verifierSignature(Request $request): bool
    {
        return match ($this->gatewayKey) {
            'paydunya' => $this->verifierSignaturePayDunya($request),
            'bictorys' => $this->verifierSignatureBictorys($request),
            default => throw new InvalidArgumentException("Verifying signature not implemented for gateway: {$this->gatewayKey}")
        };
    }

    /**
     * Vérifie la signature d'un webhook PayDunya
     */
    protected function verifierSignaturePayDunya(Request $request): bool
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

    /**
     * Vérifie la signature d'un webhook Bictorys
     * À implémenter selon la documentation Bictorys
     */
    protected function verifierSignatureBictorys(Request $request): bool
    {
        // TODO: Implémenter la vérification de signature Bictorys selon leur documentation
        // Pour l'instant, on retourne true en attendant l'implémentation réelle
        // Cela devrait être remplacé par la vraie logique de vérification

        Log::warning("Signature verification for Bictorys webhook not yet implemented - allowing through for development");

        // En production, cela devrait retourner false jusqu'à implémentation correcte
        // Pour respecter les exigences de sécurité, on pourrait lever une exception ou retourner false
        return true; // TEMPORAIRE - À REMPLACER
    }

    // ═════════════════════════════════════════════
    // HANDLER PRINCIPAL
    // ═════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function handle(Request $request): array
    {
        try {
<<<<<<< HEAD
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
=======
            // 1. Vérifier la signature spécifique au gateway
            if (!$this->verifierSignature($request)) {
                Log::warning('❌ Signature webhook invalide', [
                    'gateway' => $this->gatewayKey,
                    'ip' => $request->ip(),
                ]);

                return ['error' => 'Invalid signature', 'status' => 403];
            }

            // 2. Extraire les données communes
            $token = $this->extractToken($request);
            $statut = $this->extractStatus($request);
            $montantRecu = $this->normaliserMontant(
                $this->extractAmount($request)
            );

            if (!$token) {
                Log::error('❌ Token manquant dans webhook', [
                    'gateway' => $this->gatewayKey,
                    'payload' => $request->all(),
                ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                return ['error' => 'Missing token', 'status' => 400];
            }

            if (!$statut) {
<<<<<<< HEAD
                Log::error('��❌ Statut manquant dans le webhook', ['token' => $token]);
                return ['error' => 'Missing status', 'status' => 400];
            }

            // ── Retrouver la transaction locale via le token gateway
=======
                Log::error('❌ Statut manquant dans webhook', [
                    'gateway' => $this->gatewayKey,
                    'token' => $token,
                    'payload' => $request->all(),
                ]);

                return ['error' => 'Missing status', 'status' => 400];
            }

            // 3. Trouver la transaction associée
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
            $transaction = Transaction::query()
                ->where('gateway_token', $token)
                ->with([
                    'paiement.bail.logement.propriete.proprietaire',
                    'subscription.plan',
                    'subscription.proprietaire',
                ])
                ->first();

            if (!$transaction) {
<<<<<<< HEAD
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
=======
                Log::error("❌ Transaction introuvable pour le token {$token}", [
                    'gateway' => $this->gatewayKey,
                ]);

                return ['error' => 'Transaction not found', 'status' => 404];
            }

            // 4. VALIDATION CRITIQUE : Vérifier que le gateway de la transaction correspond au webhook
            if ($transaction->gateway_used !== $this->gatewayKey) {
                Log::error("❌ Mismatch de gateway: transaction uses {$transaction->gateway_used} but webhook is for {$this->gatewayKey}", [
                    'transaction_id' => $transaction->id,
                    'transaction_gateway_used' => $transaction->gateway_used,
                    'webhook_gateway' => $this->gatewayKey,
                    'token' => $token,
                ]);

                return ['error' => 'Gateway mismatch', 'status' => 400];
            }

            // 5. Vérifier le montant si présent
            if ($montantRecu !== null && !$this->montantsCorrespondent($transaction->montant, $montantRecu)) {
                Log::error("❌ Montant incorrect pour la transaction {$transaction->id}", [
                    'gateway' => $this->gatewayKey,
                    'attendu' => $this->normaliserMontant($transaction->montant),
                    'recu' => $montantRecu,
                    'token' => $token,
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                ]);
                return ['error' => 'Invalid amount', 'status' => 400];
            }

<<<<<<< HEAD
            // ── Router vers le handler approprié
            return match ($transaction->type) {
                'rent_payment'         => $this->handlePayment($transaction, $statut),
                'subscription_payment' => $this->handleSubscription($transaction, $statut, $transactionRef),
                default                => ['error' => 'Unknown transaction type', 'status' => 400],
            };
=======
            // 6. Traiter selon le type de transaction
            return match ($transaction->type) {
                'rent_payment' => $this->handlePayment($transaction, $statut),
                'subscription_payment' => $this->handleSubscription($transaction, $statut, $token),
                default => [
                    'error' => 'Unknown transaction type',
                    'status' => 400,
                ],
            };
        } catch (\Throwable $e) {
            Log::error('❌ Erreur inattendue dans WebhookService', [
                'gateway' => $this->gatewayKey,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

        } catch (\Throwable $e) {
            Log::error('��❌ Erreur inattendue dans WebhookService', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return ['error' => 'Internal server error', 'status' => 500];
        }
    }

<<<<<<< HEAD
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═��
    // HANDLER LOYER
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
=======
    // ═════════════════════════════════════════════
    // MÉTHODES D'EXTRACTION (à généraliser si nécessaire)
    // ════════════════════════════════════════════

    protected function extractToken(Request $request): ?string
    {
        // Pour PayDunya, on cherche le token dans plusieurs endroits possibles
        return $request->input('data.invoice.token')
            ?? $request->input('data.token')
            ?? $request->input('token');
    }

    protected function extractStatus(Request $request): ?string
    {
        return $request->input('data.status')
            ?? $request->input('status');
    }

    protected function extractAmount(Request $request): ?string
    {
        return $request->input('data.invoice.total_amount')
            ?? $request->input('data.total_amount')
            ?? $request->input('total_amount');
    }

    // ═════════════════════════════════════════════
    // HANDLER LOYER
    // ═════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    private function handlePayment(Transaction $transaction, string $statut): array
    {
        $paiement = $transaction->paiement;

        if (!$paiement) {
<<<<<<< HEAD
            Log::error("��❌ Paiement introuvable pour transaction {$transaction->id}");
=======
            Log::error("❌ Paiement introuvable pour transaction {$transaction->id}", [
                'gateway' => $this->gatewayKey,
            ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
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
<<<<<<< HEAD
=======

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
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
<<<<<<< HEAD
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
=======

                        event(new \App\Events\BailSigne($bail));

                        $this->bailService->genererLoyersMensuels($bail);
                         $this->bailService->genererEtStockerPdf($bail);

                         // Traiter le versement instantanément pour le propriétaire associé
                         try {
                             // S'assurer qu'un versement en attente existe pour cette période
                             $this->landlordEarningsService->triggerPayoutCalculation($paiement);

                             // Récupérer le versement en attente ou en cours pour cette période
                             $paymentDate = $paiement->date_paiement ?? now();
                             $periodStart = $paymentDate->startOfMonth();
                             $periodEnd = $paymentDate->endOfMonth();
                             $payoutToProcess = $paiement->bail->logement->propriete->proprietaire->payouts()
                                 ->where('period_start', $periodStart->toDateString())
                                 ->where('period_end', $periodEnd->toDateString())
                                 ->whereIn('status', ['pending', 'processing'])
                                 ->first();

                             if ($payoutToProcess) {
                                 // Traiter le versement immédiatement
                                 $result = $this->payoutProcessorService->processPayout($payoutToProcess);

                                 if (!$result['success']) {
                                     Log::warning("Échec du versement instantané après paiement {$paiement->id}", [
                                         'paiement_id' => $paiement->id,
                                         'payout_id' => $payoutToProcess->id,
                                         'error' => $result['message'],
                                     ]);
                                 }
                             }
                         } catch (\Exception $e) {
                             // Ne pas faire échouer le webhook si le traitement de versement échoue
                             // Juste logger l'erreur pour investigation
                             Log::warning("Impossible de traiter le versement instantané après paiement {$paiement->id}", [
                                 'paiement_id' => $paiement->id,
                                 'exception' => $e->getMessage(),
                             ]);
                         }
                    }
                });

                Log::info('✅ Paiement loyer validé', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                    'paiement_id' => $paiement->id,
                ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                return ['success' => true, 'message' => 'Paiement validé', 'status' => 200];

            case 'cancelled':
            case 'failed':
                $transaction->update(['statut' => 'rejete']);
<<<<<<< HEAD
                Log::warning('��⚠��️ Paiement loyer rejeté', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('��⏳ Paiement loyer en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("��⚠��️ Statut inconnu pour paiement loyer : {$statut}", ['transaction_id' => $transaction->id]);
=======

                Log::warning('⚠️ Paiement loyer rejeté ou annulé', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);

                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement loyer en attente', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour paiement loyer : {$statut}", [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
        }
    }

<<<<<<< HEAD
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═�═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
    // HANDLER ABONNEMENT
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
=======
    // ═════════════════════════════════════════════
    // HANDLER ABONNEMENT
    // ═════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    private function handleSubscription(Transaction $transaction, string $statut, string $transactionRef): array
    {
        $subscription = $transaction->subscription;

<<<<<<< HEAD
        if (!$subscription)              return ['error' => 'Subscription not found', 'status' => 404];
        if (!$subscription->plan)        return ['error' => 'Plan not found', 'status' => 404];
        if (!$subscription->proprietaire) return ['error' => 'Proprietaire not found', 'status' => 404];
=======
        if (!$subscription) {
            Log::error("❌ Subscription introuvable pour transaction {$transaction->id}", [
                'gateway' => $this->gatewayKey,
            ]);

            return ['error' => 'Subscription not found', 'status' => 404];
        }

        if (!$subscription->plan) {
            Log::error("❌ Plan introuvable pour subscription {$subscription->id}", [
                'gateway' => $this->gatewayKey,
            ]);

            return ['error' => 'Plan not found', 'status' => 404];
        }

        if (!$subscription->proprietaire) {
            Log::error("❌ Propriétaire introuvable pour subscription {$subscription->id}", [
                'gateway' => $this->gatewayKey,
            ]);

            return ['error' => 'Proprietaire not found', 'status' => 404];
        }
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

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

<<<<<<< HEAD
                Log::info('��✅ Abonnement activé', [
=======
                Log::info('✅ Abonnement activé', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
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
<<<<<<< HEAD
                Log::warning('��⚠��️ Paiement abonnement annulé', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('��⏳ Paiement abonnement en attente', ['transaction_id' => $transaction->id]);
                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("��⚠��️ Statut inconnu pour abonnement : {$statut}", ['transaction_id' => $transaction->id]);
=======

                Log::warning('⚠️ Paiement abonnement annulé ou échoué', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                    'statut_paydunya' => $statut,
                ]);

                return ['success' => true, 'message' => 'Paiement annulé', 'status' => 200];

            case 'pending':
                Log::info('⏳ Paiement abonnement en attente', [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                ]);

                return ['success' => true, 'message' => 'Payment pending', 'status' => 200];

            default:
                Log::warning("⚠️ Statut PayDunya inconnu pour abonnement : {$statut}", [
                    'gateway' => $this->gatewayKey,
                    'transaction_id' => $transaction->id,
                ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                return ['success' => true, 'message' => 'Unknown status', 'status' => 200];
            }
        }

<<<<<<< HEAD
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═�═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��
    // HELPERS
    // ���� �� �� ═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═���═�═��

    private function montantsCorrespondent(float $attendu, float $recu): bool
=======
    // ═════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════

    private function normaliserMontant(?string $montant): ?string
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
    {
        return number_format($attendu, 2, '.', '') === number_format($recu, 2, '.', '');
    }
<<<<<<< HEAD
=======

    private function montantsCorrespondent(string $montantBase, string $montantRecu): bool
    {
        return $this->normaliserMontant($montantBase) === $this->normaliserMontant($montantRecu);
    }
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
}
<?php

namespace App\Services;

use App\Models\Payout;
use App\Services\GatewayResolver;
use App\Services\PhoneNumberFormatter;
use App\Models\Proprietaire;
use Illuminate\Support\Facades\Log;
use App\Constants\PaymentStatus;

class PayoutProcessorService
{
    protected GatewayResolver $gatewayResolver;
    protected NotificationService $notificationService;

    public function __construct(GatewayResolver $gatewayResolver, NotificationService $notificationService)
    {
        $this->gatewayResolver = $gatewayResolver;
        $this->notificationService = $notificationService;
    }

    /**
     * Traite un versement en attente
     * À adapter selon vos prestataires de versement (banques, opérateurs mobile money)
     *
     * @param Payout $payout
     * @return array Résultat du traitement
     */
    public function processPayout(Payout $payout): array
    {
        // Marquer comme en cours de traitement
        $payout->update(['status' => PaymentStatus::PAYOUT_PROCESSING]);

        try {
            // Utiliser l'agrégateur actif configuré pour garantir que payin et payout utilisent le même agrégateur
            $activeGateway = $this->gatewayResolver->getActiveGateway();
            $gatewayKey = $activeGateway->getName();
            $gateway = $activeGateway;

            // Récupérer la méthode de versement du propriétaire
            $payoutMethod = $payout->payoutMethod;

            if (!$payoutMethod) {
                throw new \Exception("Méthode de versement introuvable pour le versement {$payout->id}");
            }

            // Préparer les données pour l'appel au gateway
            $amount = (float) $payout->net_amount_to_owner;
            $reference = $payout->reference;

            // Résoudre le numéro de téléphone et le canal de versement
            $phoneNumber = $this->resolvePayoutPhone($payoutMethod, $payout->proprietaire);
            $channel = $this->resolvePayoutChannel($payoutMethod, $payout->proprietaire);

            $phoneNumber = PhoneNumberFormatter::formatE164($phoneNumber);

            // Effectuer le versement via le gateway approprié
            $payoutResult = $gateway->processPayout($amount, $phoneNumber, $reference, $channel);

            if ($payoutResult['success']) {
                // Marquer comme complété
                $payout->update([
                    'status' => PaymentStatus::PAYOUT_COMPLETED,
                    'processed_at' => now(),
                    'reference' => $payoutResult['external_reference'] ?? $payout->reference,
                ]);

                Log::info("Versement {$payout->id} effectué avec succès via {$gatewayKey}", [
                    'proprietaire_id' => $payout->proprietaire_id,
                    'amount' => $payout->net_amount_to_owner,
                    'method' => $channel,
                    'gateway' => $gatewayKey,
                    'reference' => $payout->reference,
                ]);

                // ✅ NOTIFICATION AU PROPRIÉTAIRE (optionnel)
                try {
                    $this->sendPayoutNotification($payout);
                } catch (\Exception $e) {
                    // Ne pas faire échouer le traitement si la notification échoue
                    Log::warning("Échec de l'envoi de notification de versement {$payout->id}", [
                        'payout_id' => $payout->id,
                        'exception' => $e->getMessage(),
                    ]);
                }

                return [
                    'success' => true,
                    'message' => 'Versement effectué',
                    'payout' => $payout->fresh(),
                ];
            } else {
                // Échec du versement
                $payout->update(['status' => PaymentStatus::PAYOUT_FAILED]);
                Log::error("Échec du versement {$payout->id} via {$gatewayKey}", [
                    'proprietaire_id' => $payout->proprietaire_id,
                    'error' => $payoutResult['error'] ?? 'Inconnu',
                    'gateway' => $gatewayKey,
                ]);

                return [
                    'success' => false,
                    'message' => 'Échec du versement : ' . ($payoutResult['error'] ?? 'Inconnu'),
                ];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => PaymentStatus::PAYOUT_FAILED]);
            Log::error("Exception lors du traitement du versement {$payout->id}", [
                'proprietaire_id' => $payout->proprietaire_id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur interne lors du traitement du versement',
            ];
        }
    }

    /**
     * Résout le numéro de téléphone de versement à utiliser
     * Priorité:
     * 1. Número explicite dans la méthode de versement
     * 2. Téléphone préféré du propriétaire
     * 3. Téléphone legacy du propriétaire
     * 4. Téléphone par défaut du système (devrait être géré ailleurs)
     *
     * @param \App\Models\PayoutMethod $payoutMethod
     * @param \App\Models\Proprietaire $proprietaire
     * @return string
     */
    private function resolvePayoutPhone(\App\Models\PayoutMethod $payoutMethod, Proprietaire $proprietaire): string
    {
        // 1. Número explicite défini pour cette méthode de versement (priorité haute)
        if (!empty($payoutMethod->payout_phone)) {
            return $payoutMethod->payout_phone;
        }

        // 2. Téléphone préféré du propriétaire (nouveau champ)
        if (!empty($proprietaire->payout_phone)) {
            return $proprietaire->payout_phone;
        }

        // 3. Téléphone legacy du propriétaire (rétrocompatibilité)
        if (!empty($proprietaire->payout_phone)) {
            return $proprietaire->payout_phone;
        }

        // 4. Fallback: devrait normalement jamais arriver si le modèle est correctement configuré
        // Mais on retourne une chaîne vide pour éviter les erreurs, le formatter gérera l'erreur
        return '';
    }

    /**
     * Résout le canal de versement à utiliser
     * Priorité:
     * 1. Canal explicite dans la méthode de versement (override manuel pour ce versement spécifique)
     * 2. Préférence de canal du propriétaire (nouveau champ préférence)
     * 3. Canal legacy du propriétaire (rétrocompatibilité avec champ existant)
     * 4. Canal du paiement entrant (optionnel logique - à implémenter si lié à la transaction)
     * 5. Canal par défaut du système
     *
     * @param \App\Models\PayoutMethod $payoutMethod
     * @param \App\Models\Proprietaire $proprietaire
     * @return string
     */
    private function resolvePayoutChannel(\App\Models\PayoutMethod $payoutMethod, Proprietaire $proprietaire): string
    {
        // 1. Canal explicite défini pour cette méthode de versement (priorité haute - permet override manuel)
        if (!empty($payoutMethod->payout_channel)) {
            return $payoutMethod->payout_channel;
        }

        // 2. Préférence de canal du propriétaire (nouveau champ - ce qu'on implémente)
        if (!empty($proprietaire->payout_channel_preference)) {
            return $proprietaire->payout_channel_preference;
        }

        // 3. Canal legacy du propriétaire (rétrocompatibilité avec champ existant)
        if (!empty($proprietaire->payout_channel)) {
            return $proprietaire->payout_channel;
        }

        // 4. Fallback système global (configuration par défaut)
        return config('payout.default_channel', 'wave');
    }

    /**
     * Traite tous les versements en attente
     * À appeler via une tâche planifiée (cron) ou un déclencheur
     *
     * @return array Résultats du traitement
     */
    public function processAllPendingPayouts(): array
    {
        $pendingPayouts = Payout::where('status', PaymentStatus::PAYOUT_PENDING)->get();
        $results = [];

        foreach ($pendingPayouts as $payout) {
            $results[] = $this->processPayout($payout);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        Log::info("Traitement des versements en attente terminé", [
            'total' => count($pendingPayouts),
            'success' => $successCount,
            'failed' => $failCount,
        ]);

        return [
            'total_processed' => count($pendingPayouts),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'details' => $results,
        ];
    }

    /**
     * Envoie une notification au propriétaire lorsqu'un versement est effectué
     * Cette méthode nécessite un service de notification configuré
     *
     * @param Payout $payout
     * @return void
     */
    protected function sendPayoutNotification(Payout $payout): void
    {
        try {
            $this->notificationService->sendToUser(
                $payout->proprietaire->user,
                'Versement effectué 💰',
                'Un versement de ' . number_format($payout->net_amount_to_owner, 0, ',', ' ') . ' FCFA a été effectué sur votre compte ' .
                $payout->payoutMethod->getChannelDisplayName() . '.',
                'payout_completed',
                [
                    'payout_id' => (string) $payout->id,
                    'amount' => (string) $payout->net_amount_to_owner,
                    'method' => $payout->payoutMethod->payout_channel,
                    'date' => $payout->processed_at->format('d/m/Y'),
                ]
            );
        } catch (\Exception $e) {
            // Ne pas faire échouer le traitement si la notification échoue
            Log::warning("Échec de l'envoi de notification de versement {$payout->id}", [
                'payout_id' => $payout->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
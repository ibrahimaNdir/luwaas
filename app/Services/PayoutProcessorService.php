<?php

namespace App\Services;

use App\Models\Payout;
use App\Services\GatewayResolver;
use Illuminate\Support\Facades\Log;

class PayoutProcessorService
{
    protected GatewayResolver $gatewayResolver;

    public function __construct(GatewayResolver $gatewayResolver)
    {
        $this->gatewayResolver = $gatewayResolver;
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
        $payout->update(['status' => 'processing']);

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
            $phoneNumber = $payoutMethod->payout_phone;
            $channel = $payoutMethod->payout_channel;

            // Format du numéro de téléphone (devrait déjà être en format E.164)
            // S'assurer que le numéro commence par le code pays du Sénégal (+221)
            if (!preg_match('/^\+221/', $phoneNumber)) {
                // Si le numéro commence par 0, remplacer par +221
                if (preg_match('/^0/', $phoneNumber)) {
                    $phoneNumber = '+221' . substr($phoneNumber, 1);
                }
                // Si le numéro commence par 221 mais sans +, ajouter le +
                elseif (preg_match('/^221/', $phoneNumber)) {
                    $phoneNumber = '+' . $phoneNumber;
                }
                // Sinon, ajouter +221 au début
                else {
                    $phoneNumber = '+221' . $phoneNumber;
                }
            }

            // Effectuer le versement via le gateway approprié
            $payoutResult = $gateway->processPayout($amount, $phoneNumber, $reference, $channel);

            if ($payoutResult['success']) {
                // Marquer comme complété
                $payout->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'reference' => $payoutResult['external_reference'] ?? $payout->reference,
                ]);

                Log::info("Versement {$payout->id} effectué avec succès via {$gatewayKey}", [
                    'proprietaire_id' => $payout->proprietaire_id,
                    'amount' => $payout->net_amount_to_owner,
                    'method' => $payout->payoutMethod->payout_channel,
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
                $payout->update(['status' => 'failed']);
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
            $payout->update(['status' => 'failed']);
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
     * Traite tous les versements en attente
     * À appeler via une tâche planifiée (cron) ou un déclencheur
     *
     * @return array Résultats du traitement
     */
    public function processAllPendingPayouts(): array
    {
        $pendingPayouts = Payout::where('status', 'pending')->get();
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
        // Cette méthode nécessite un service de notification
        // Vous pouvez l'implémenter selon votre système de notification existant
        // Exemple avec un service de notification générique :

        /*
        if (app()->bound('notification.service')) {
            $notificationService = app('notification.service');
            $notificationService->send(
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
        }
        */

        // Pour l'instant, on laisse vide - à implémenter selon votre système
        // Ou on peut simplement logger l'information
        Log::info("Notification de versement envoyée (à implémenter)", [
            'payout_id' => $payout->id,
            'proprietaire_id' => $payout->proprietaire_id,
            'amount' => $payout->net_amount_to_owner,
        ]);
    }
}
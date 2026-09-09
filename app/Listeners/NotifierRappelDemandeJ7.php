<?php

namespace App\Listeners;

use App\Events\DemandeRappelJ7;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierRappelDemandeJ7 implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification de rappel J7 au locataire concernant sa demande en attente
     */
    public function handle(DemandeRappelJ7 $event)
    {
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->locataire ||
                !$demande->locataire->user ||
                !$demande->logement) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeRappelJ7', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user),
                    'has_logement' => !is_null($demande->logement)
                ]);
                return;
            }

            $locataire = $demande->locataire->user;
            $logement = $demande->logement;

            // Construire le message de rappel
            $logementInfo = $logement->typelogement . ' ' . $logement->numero . ' - ' . $logement->propriete->titre ?? '';
            $message = "Votre demande pour le logement {$logementInfo} est toujours en attente après 7 jours. N'hésitez pas à suivre up ou à explorer d'autres options.";

            $this->notificationService->sendToUser(
                $locataire,
                "Rappel : demande en attente",
                $message,
                "demande_rappel_j7",
                [
                    'demande_id' => (string) $demande->id,
                    'logement_id' => (string) $demande->logement_id,
                ]
            );
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierRappelDemandeJ7: ' . $e->getMessage(), [
                'event' => 'DemandeRappelJ7',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}
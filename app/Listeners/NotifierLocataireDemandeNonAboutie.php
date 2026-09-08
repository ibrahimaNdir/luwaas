<?php

namespace App\Listeners;

use App\Events\DemandeNonAboutie;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierLocataireDemandeNonAboutie implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification au locataire quand la demande devient non aboutie
     */
    public function handle(DemandeNonAboutie $event): void
    {
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->locataire ||
                !$demande->locataire->user ||
                !$demande->logement) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeNonAboutie', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user),
                    'has_logement' => !is_null($demande->logement)
                ]);
                return;
            }

            $locataire = $demande->locataire->user;

            $this->notificationService->sendToUser(
                $locataire,
                "Demande non aboutie ⚠️",
                "Votre demande acceptée n'a pas abouti à la création d'un bail dans le délai prévu.",
                "demande_non_aboutie",
                [
                    'demande_id' => (string) $demande->id,
                    'logement_id' => (string) $demande->logement_id,
                ]
            );
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierLocataireDemandeNonAboutie: ' . $e->getMessage(), [
                'event' => 'DemandeNonAboutie',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}
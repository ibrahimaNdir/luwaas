<?php

namespace App\Listeners;

use App\Events\DemandeAcceptee;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierLocataireDemandeAcceptee implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification au locataire quand demande acceptée
     */
    public function handle(DemandeAcceptee $event)
    {
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->locataire ||
                !$demande->locataire->user) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeAcceptee', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user)
                ]);
                return;
            }

            $locataire = $demande->locataire->user;

            $this->notificationService->sendToUser(
                $locataire,
                "Demande acceptée ! ✅",
                "Le propriétaire a accepté votre demande. Vous pouvez maintenant organiser une visite.",
                "demande_acceptee",
                [
                    'demande_id' => (string) $demande->id,
                    'logement_id' => (string) $demande->logement_id,
                ]
            );
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierLocataireDemandeAcceptee: ' . $e->getMessage(), [
                'event' => 'DemandeAcceptee',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}

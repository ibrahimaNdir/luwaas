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
        $demande = $event->demande;
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
    }
}

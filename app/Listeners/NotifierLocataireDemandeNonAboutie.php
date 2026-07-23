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
        $demande = $event->demande;
        $locataire = $demande->locataire?->user;

        if (!$locataire) {
            return;
        }

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
    }
}
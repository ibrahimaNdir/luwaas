<?php

namespace App\Listeners;

use App\Events\DemandeRefusee;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierLocataireDemandeRefusee implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification au locataire quand demande refusée
     */
    public function handle(DemandeRefusee $event)
    {
        $demande = $event->demande;
        $locataire = $demande->locataire->user;

        $this->notificationService->sendToUser(
            $locataire,
            "Demande refusée ❌",
            "Le propriétaire n'a pas donné suite à votre demande pour le moment.",
            "demande_refusee",
            [
                'demande_id' => (string) $demande->id,
                'logement_id' => (string) $demande->logement_id,
            ]
        );
    }
}

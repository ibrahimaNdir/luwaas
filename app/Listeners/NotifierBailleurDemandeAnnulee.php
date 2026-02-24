<?php

namespace App\Listeners;

use App\Events\DemandeAnnulee;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierBailleurDemandeAnnulee implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification au bailleur quand une demande est annulée
     */
    public function handle(DemandeAnnulee $event)
    {
        $demande = $event->demande;
        $proprietaire = $demande->proprietaire->user;
        $locataireName = $demande->locataire->user->prenom
            . ' ' . $demande->locataire->user->nom;

        $this->notificationService->sendToUser(
            $proprietaire,
            "Demande annulée ❌",
            "$locataireName a annulé sa demande de visite pour votre logement.",
            "demande_annulee",
            [
                'demande_id'  => (string) $demande->id,
                'logement_id' => (string) $demande->logement_id,
            ]
        );
    }
}

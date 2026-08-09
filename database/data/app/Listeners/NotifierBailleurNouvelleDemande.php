<?php

namespace App\Listeners;

use App\Events\DemandeLogementRecue;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierBailleurNouvelleDemande implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoie une notification au bailleur quand nouvelle demande
     */
    public function handle(DemandeLogementRecue $event)
    {
        $demande = $event->demande;
        $logement = $demande->logement;
        $locataire = $demande->locataire->user;
        $proprietaire = $demande->proprietaire->user;

        // Construire le message
        $nomComplet = ucfirst($locataire->prenom) . ' ' . ucfirst($locataire->nom);
        $typeLogement = ucfirst($logement->typelogement);
        $message = "{$nomComplet} souhaite visiter votre {$typeLogement} {$logement->numero} - {$logement->propriete->titre}.";

        // Envoyer la notification
        $this->notificationService->sendToUser(
            $proprietaire,
            "Nouvelle demande !",
            $message,
            "nouvelle_demande",
            [
                'demande_id' => (string) $demande->id,
                'logement_id' => (string) $logement->id,
                'logement_numero' => $logement->numero,
                'logement_type' => $typeLogement,
                'propriete_nom' => $logement->propriete->titre,
                'locataire_id' => (string) $demande->locataire_id,
                'locataire_nom' => $nomComplet,
                'locataire_telephone' => $locataire->telephone ?? 'Non renseigné'
            ]
        );
    }
}

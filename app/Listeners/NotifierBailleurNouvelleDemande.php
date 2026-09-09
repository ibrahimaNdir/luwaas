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
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->logement ||
                !$demande->locataire ||
                !$demande->locataire->user ||
                !$demande->propriete ||
                !$demande->propriete->proprietaire ||
                !$demande->propriete->proprietaire->user) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeLogementRecue', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_logement' => !is_null($demande->logement),
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user),
                    'has_propriete' => !is_null($demande->propriete),
                    'has_proprietaire' => !is_null($demande->propriete?->proprietaire),
                    'has_proprietaire_user' => !is_null($demande->propriete?->proprietaire?->user)
                ]);
                return;
            }

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
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierBailleurNouvelleDemande: ' . $e->getMessage(), [
                'event' => 'DemandeLogementRecue',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}

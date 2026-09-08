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
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->proprietaire ||
                !$demande->proprietaire->user ||
                !$demande->locataire ||
                !$demande->locataire->user) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeAnnulee', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_proprietaire' => !is_null($demande->proprietaire),
                    'has_proprietaire_user' => !is_null($demande->proprietaire?->user),
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user)
                ]);
                return;
            }

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
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierBailleurDemandeAnnulee: ' . $e->getMessage(), [
                'event' => 'DemandeAnnulee',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}

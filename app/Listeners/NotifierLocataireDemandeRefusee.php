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
     * Envoie une notification au locataire quand demande refusée,
     * avec un message adapté selon la raison du refus.
     */
    public function handle(DemandeRefusee $event)
    {
        try {
            $demande = $event->demande;

            // Validation défensive des relations
            if (!$demande->locataire ||
                !$demande->locataire->user) {

                Log::warning('Notification ignorée: relations manquantes pour DemandeRefusee', [
                    'demande_id' => $demande->id ?? 'unknown',
                    'has_locataire' => !is_null($demande->locataire),
                    'has_locataire_user' => !is_null($demande->locataire?->user)
                ]);
                return;
            }

            $locataire = $demande->locataire->user;

            if ($demande->motif_refus === 'other_lease_created') {
                $titre = "Logement déjà loué";
                $message = "Ce logement a été loué à un autre candidat. N'hésitez pas à consulter d'autres annonces.";
            } else {
                $titre = "Demande refusée ❌";
                $message = "Le propriétaire n'a pas donné suite à votre demande pour le moment.";
            }

            $this->notificationService->sendToUser(
                $locataire,
                $titre,
                $message,
                "demande_refusee",
                [
                    'demande_id'  => (string) $demande->id,
                    'logement_id' => (string) $demande->logement_id,
                    'motif_refus' => (string) $demande->motif_refus,
                ]
            );
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierLocataireDemandeRefusee: ' . $e->getMessage(), [
                'event' => 'DemandeRefusee',
                'demande_id' => $event->demande->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}
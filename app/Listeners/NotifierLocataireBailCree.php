<?php

namespace App\Listeners;

use App\Events\BailCree;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierLocataireBailCree implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notifie le locataire quand le bailleur crée le bail
     */
    public function handle(BailCree $event): void
    {
        try {
            $bail = $event->bail;

            // Validation défensive des relations
            if (!$bail->locataire ||
                !$bail->locataire->user) {

                Log::warning('Notification ignorée: relations manquantes pour BailCree', [
                    'bail_id' => $bail->id ?? 'unknown',
                    'has_locataire' => !is_null($bail->locataire),
                    'has_locataire_user' => !is_null($bail->locataire?->user)
                ]);
                return;
            }

            $logementInfo = $bail->logement
                ? "{$bail->logement->typelogement} {$bail->logement->numero}"
                : "votre logement";

            $montantTotal = number_format($bail->montant_total, 0, ',', ' ');

            // Notifier le LOCATAIRE
            if ($bail->locataire && $bail->locataire->user) {
                $this->notificationService->sendToUser(
                    $bail->locataire->user,
                    "Contrat de location reçu 📄",
                    "Un contrat de bail pour {$logementInfo} vous a été envoyé. Consultez et procédez au paiement de {$montantTotal} FCFA.",
                    "bail_cree",
                    [
                        'bail_id'       => (string) $bail->id,
                        'logement_id'   => (string) $bail->logement_id,
                        'montant_total' => (string) $bail->montant_total,
                        'pdf_url'       => $bail->pdf_url ?? null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Empêcher qu'une exception de listener ne bloque les autres listeners
            Log::error('Erreur dans NotifierLocataireBailCree: ' . $e->getMessage(), [
                'event' => 'BailCree',
                'bail_id' => $event->bail->id ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas relancer l'exception pour permettre aux autres listeners de s'exécuter
        }
    }
}

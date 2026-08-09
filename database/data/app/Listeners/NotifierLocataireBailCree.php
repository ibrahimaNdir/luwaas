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
        $bail = $event->bail;

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
    }
}

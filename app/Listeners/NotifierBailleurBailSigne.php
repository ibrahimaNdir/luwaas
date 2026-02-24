<?php

namespace App\Listeners;

use App\Events\BailSigne;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifierBailleurBailSigne implements ShouldQueue
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notifie le bailleur ET le locataire quand le paiement du bail est reçu
     */
    public function handle(BailSigne $event): void
    {
        $bail = $event->bail;

        $locataireNom = ucfirst($bail->locataire->user->prenom)
            . ' ' . ucfirst($bail->locataire->user->nom);

        $logementInfo = $bail->logement
            ? "{$bail->logement->typelogement} {$bail->logement->numero}"
            : "votre logement";

        $montant = number_format($bail->montant_total, 0, ',', ' ');

        // 1. Notifier le BAILLEUR
        if ($bail->proprietaire && $bail->proprietaire->user) {
            $this->notificationService->sendToUser(
                $bail->proprietaire->user,
                "Bail signé et payé ✅",
                "{$locataireNom} a payé {$montant} FCFA pour {$logementInfo}. Le bail est actif !",
                "paiement_bail",
                [
                    'bail_id'       => (string) $bail->id,
                    'logement_id'   => (string) $bail->logement_id,
                    'montant'       => (string) $bail->montant_total,
                    'locataire_nom' => $locataireNom,
                ]
            );
        }

        // 2. Notifier le LOCATAIRE
        if ($bail->locataire && $bail->locataire->user) {
            $this->notificationService->sendToUser(
                $bail->locataire->user,
                "Paiement confirmé 🏠",
                "Votre paiement de {$montant} FCFA a été reçu. Votre bail pour {$logementInfo} est maintenant actif.",
                "bail_actif",
                [
                    'bail_id'     => (string) $bail->id,
                    'logement_id' => (string) $bail->logement_id,
                    'montant'     => (string) $bail->montant_total,
                    'pdf_url'     => $bail->pdf_url ?? null,
                ]
            );
        }
    }
}

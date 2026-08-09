<?php

namespace App\Listeners;

use App\Events\DemandeRappelJ7;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifierRappelDemandeJ7
{
    public function __construct(protected NotificationService $notificationService) {}

    public function handle(DemandeRappelJ7 $event): void
    {
        $demande = $event->demande;

        $proprietaire = $demande->proprietaire?->user;
        $locataire    = $demande->locataire?->user;

        if ($proprietaire) {
            $this->notificationService->sendToUser(
                $proprietaire,
                'Rappel : demande sans suite',
                "La demande de {$locataire?->prenom} {$locataire?->nom} pour votre logement est acceptée depuis 7 jours sans bail créé.",
                'rappel_demande_j7',
                ['demande_id' => $demande->id]
            );
        }

        if ($locataire) {
            $this->notificationService->sendToUser(
                $locataire,
                'Votre demande est toujours en attente',
                "Votre demande de location est acceptée depuis 7 jours. Prenez contact avec le bailleur pour finaliser.",
                'rappel_demande_j7',
                ['demande_id' => $demande->id]
            );
        }

        Log::info("Rappel J+7 envoyé pour la demande #{$demande->id}");
    }
}
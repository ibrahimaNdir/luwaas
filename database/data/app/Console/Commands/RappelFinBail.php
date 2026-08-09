<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bail;
use App\Services\NotificationService;
use Carbon\Carbon;

class RappelFinBail extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'luwaas:rappel-fin-bail';

    /**
     * Description de la commande
     */
    protected $description = 'Envoie des rappels pour les baux expirant dans 30 jours';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Exécute la commande
     */
    public function handle()
    {
        $this->info('🔍 Vérification des baux expirant bientôt...');

        // Date dans 30 jours
        $dateExpiration = Carbon::now()->addDays(30)->format('Y-m-d');

        // Trouver tous les baux actifs expirant dans 30 jours
        $baux = Bail::where('statut', 'actif')
            ->whereDate('date_fin', $dateExpiration)
            ->with(['locataire.user', 'proprietaire.user', 'logement'])
            ->get();

        $count = 0;

        foreach ($baux as $bail) {
            $logementInfo = $bail->logement 
                ? "{$bail->logement->typelogement} {$bail->logement->numero}" 
                : "votre logement";

            // Notifier le LOCATAIRE
            if ($bail->locataire && $bail->locataire->user) {
                $this->notificationService->sendToUser(
                    $bail->locataire->user,
                    "⚠️ Bail bientôt expiré",
                    "Votre bail pour {$logementInfo} expire le {$bail->date_fin}. Pensez à renouveler ou contacter votre propriétaire.",
                    "fin_bail_proche",
                    [
                        'bail_id' => (string) $bail->id,
                        'date_fin' => $bail->date_fin,
                        'logement_id' => (string) $bail->logement_id,
                        'jours_restants' => 30,
                    ]
                );
                $count++;
            }

            // Notifier le BAILLEUR
            if ($bail->proprietaire && $bail->proprietaire->user) {
                $locataireNom = $bail->locataire->user 
                    ? ucfirst($bail->locataire->user->prenom) . ' ' . ucfirst($bail->locataire->user->nom)
                    : 'Votre locataire';

                $this->notificationService->sendToUser(
                    $bail->proprietaire->user,
                    "⚠️ Bail bientôt expiré",
                    "Le bail de {$locataireNom} pour {$logementInfo} expire le {$bail->date_fin}. Pensez au renouvellement.",
                    "fin_bail_proche_bailleur",
                    [
                        'bail_id' => (string) $bail->id,
                        'date_fin' => $bail->date_fin,
                        'logement_id' => (string) $bail->logement_id,
                        'locataire_nom' => $locataireNom,
                    ]
                );
                $count++;
            }
        }

        $this->info("✅ {$count} notifications de fin de bail envoyées.");
        return 0;
    }
}

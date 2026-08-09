<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bail;
use App\Services\NotificationService;
use Carbon\Carbon;

class RappelDebutBail extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'luwaas:rappel-debut-bail';

    /**
     * Description de la commande
     */
    protected $description = 'Active les baux qui commencent aujourd\'hui et notifie bailleur et locataire';

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
        $this->info('🔍 Vérification des baux débutant aujourd\'hui...');

        // Date d'aujourd'hui
        $dateAujourdhui = Carbon::now()->format('Y-m-d');

        // Trouver tous les baux signés qui commencent aujourd'hui
        $baux = Bail::where('statut', 'signe')
            ->whereDate('date_debut', $dateAujourdhui)
            ->with(['locataire.user', 'proprietaire.user', 'logement'])
            ->get();

        $count = 0;

        foreach ($baux as $bail) {
            // 1. Activer le bail
            $bail->update(['statut' => 'actif']);

            $logementInfo = $bail->logement
                ? "{$bail->logement->typelogement} {$bail->logement->numero}"
                : "votre logement";

            // 2. Notifier le LOCATAIRE
            if ($bail->locataire && $bail->locataire->user) {
                $this->notificationService->sendToUser(
                    $bail->locataire->user,
                    "🏠 Votre bail commence aujourd'hui !",
                    "Votre bail pour {$logementInfo} est maintenant actif. Bonne installation !",
                    "debut_bail",
                    [
                        'bail_id'     => (string) $bail->id,
                        'date_debut'  => $bail->date_debut,
                        'logement_id' => (string) $bail->logement_id,
                    ]
                );
                $count++;
            }

            // 3. Notifier le BAILLEUR
            if ($bail->proprietaire && $bail->proprietaire->user) {
                $locataireNom = $bail->locataire->user
                    ? ucfirst($bail->locataire->user->prenom) . ' ' . ucfirst($bail->locataire->user->nom)
                    : 'Votre locataire';

                $this->notificationService->sendToUser(
                    $bail->proprietaire->user,
                    "🏠 Bail actif aujourd'hui !",
                    "Le bail de {$locataireNom} pour {$logementInfo} commence aujourd'hui.",
                    "debut_bail_bailleur",
                    [
                        'bail_id'       => (string) $bail->id,
                        'date_debut'    => $bail->date_debut,
                        'logement_id'   => (string) $bail->logement_id,
                        'locataire_nom' => $locataireNom,
                    ]
                );
                $count++;
            }

            $this->info("✅ Bail #{$bail->id} activé pour {$locataireNom}");
        }

        $this->info("✅ {$count} notifications de début de bail envoyées.");
        return 0;
    }
}

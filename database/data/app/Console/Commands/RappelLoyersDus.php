<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bail;
use App\Services\NotificationService;
use Carbon\Carbon;

class RappelLoyersDus extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'luwaas:rappel-loyers';

    /**
     * Description de la commande
     */
    protected $description = 'Envoie des rappels pour les loyers dus dans 3 jours';

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
        $this->info('🔍 Vérification des loyers à payer...');

        // Date dans 3 jours
        $dateEcheance = Carbon::now()->addDays(3)->format('Y-m-d');

        // Trouver tous les baux actifs avec loyer dû dans 3 jours
        // (Adapte selon ta structure DB - exemple si tu as une table paiements)
        $baux = Bail::where('statut', 'actif')
            ->whereHas('paiements', function($query) use ($dateEcheance) {
                $query->where('date_echeance', $dateEcheance)
                      ->whereIn('statut', ['impayé', 'partiel']);
            })
            ->with(['locataire.user', 'proprietaire.user'])
            ->get();

        $count = 0;

        foreach ($baux as $bail) {
            // Envoyer notif au LOCATAIRE
            if ($bail->locataire && $bail->locataire->user) {
                $this->notificationService->sendToUser(
                    $bail->locataire->user,
                    "Rappel : Loyer à payer",
                    "Votre loyer de {$bail->montant_loyer} FCFA est dû le {$dateEcheance}. Pensez à effectuer le paiement.",
                    "rappel_loyer",
                    [
                        'bail_id' => (string) $bail->id,
                        'montant' => $bail->montant_loyer,
                        'date_echeance' => $dateEcheance,
                    ]
                );
                $count++;
            }

            // Envoyer notif au BAILLEUR (optionnel)
            if ($bail->proprietaire && $bail->proprietaire->user) {
                $this->notificationService->sendToUser(
                    $bail->proprietaire->user,
                    "Rappel : Loyer à recevoir",
                    "Le loyer de {$bail->montant_loyer} FCFA est dû dans 3 jours pour votre logement.",
                    "rappel_loyer_bailleur",
                    [
                        'bail_id' => (string) $bail->id,
                        'montant' => $bail->montant_loyer,
                    ]
                );
            }
        }

        $this->info("✅ {$count} notifications de rappel envoyées.");
        return 0;
    }
}

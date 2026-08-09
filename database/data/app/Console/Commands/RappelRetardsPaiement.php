<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Paiement;
use App\Services\NotificationService;
use Carbon\Carbon;

class RappelRetardsPaiement extends Command
{
    /**
     * Nom et signature de la commande
     */
    protected $signature = 'luwaas:rappel-retards';

    /**
     * Description de la commande
     */
    protected $description = 'Envoie des rappels pour les paiements en retard de plus de 7 jours';

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
        $this->info('🔍 Vérification des retards de paiement...');

        // Date il y a 7 jours
        $dateRetard = Carbon::now()->subDays(7)->format('Y-m-d');

        // Trouver tous les paiements en retard (date_echeance dépassée de + de 7 jours)
        $paiementsEnRetard = Paiement::whereIn('statut', ['impayé', 'en_retard', 'partiel'])
            ->whereDate('date_echeance', '<=', $dateRetard)
            ->with(['bail.locataire.user', 'bail.proprietaire.user', 'bail.logement'])
            ->get();

        $count = 0;

        foreach ($paiementsEnRetard as $paiement) {
            $bail = $paiement->bail;
            if (!$bail) continue;

            $joursRetard = Carbon::parse($paiement->date_echeance)->diffInDays(Carbon::now());
            $logementInfo = $bail->logement 
                ? "{$bail->logement->typelogement} {$bail->logement->numero}" 
                : "votre logement";

            // Notifier le LOCATAIRE (en retard)
            if ($bail->locataire && $bail->locataire->user) {
                $this->notificationService->sendToUser(
                    $bail->locataire->user,
                    "🚨 Retard de paiement",
                    "Vous avez un retard de {$joursRetard} jours pour votre loyer de {$paiement->montant_restant} FCFA ({$logementInfo}). Merci de régulariser au plus vite.",
                    "retard_paiement",
                    [
                        'paiement_id' => (string) $paiement->id,
                        'bail_id' => (string) $bail->id,
                        'montant' => $paiement->montant_restant,
                        'jours_retard' => $joursRetard,
                        'date_echeance' => $paiement->date_echeance,
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
                    "⚠️ Loyer impayé",
                    "{$locataireNom} a un retard de {$joursRetard} jours pour son loyer de {$paiement->montant_restant} FCFA ({$logementInfo}).",
                    "retard_paiement_bailleur",
                    [
                        'paiement_id' => (string) $paiement->id,
                        'bail_id' => (string) $bail->id,
                        'montant' => $paiement->montant_restant,
                        'jours_retard' => $joursRetard,
                        'locataire_nom' => $locataireNom,
                    ]
                );
                $count++;
            }
        }

        $this->info("✅ {$count} notifications de retard envoyées.");
        return 0;
    }
}

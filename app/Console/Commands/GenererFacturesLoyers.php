<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bail;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenererFacturesLoyers extends Command
{
    // Le nom de la commande pour la lancer dans le terminal
    protected $signature = 'luwaas:generer-loyers';
    protected $description = 'Génère les paiements de loyer 5 jours avant la date d\'échéance';

    public function handle()
    {
        $this->info('Démarrage de la génération des loyers...');
        
        // On cible la date dans exactement 5 jours
        $dateCible = now()->addDays(5);
        $jourCible = $dateCible->day; // On récupère juste le jour (ex: le 5)
        $periodeCible = $dateCible->isoFormat('MMMM YYYY');

        // On cherche tous les baux actifs dont le jour d'échéance correspond
        $baux = Bail::where('statut', 'actif')
                    ->where('jour_echeance', $jourCible)
                    ->get();

        $compteur = 0;

        foreach ($baux as $bail) {
            // VERROU DE SÉCURITÉ : Vérifier si la facture de ce mois n'existe pas déjà
            $factureExiste = Paiement::where('bail_id', $bail->id)
                                    ->where('periode', $periodeCible)
                                    ->where('type', 'loyer_mensuel')
                                    ->exists();

            if (!$factureExiste) {
                Paiement::create([
                    'locataire_id' => $bail->locataire_id,
                    'bail_id' => $bail->id,
                    'type' => 'loyer_mensuel',
                    'montant_attendu' => $bail->montant_loyer + $bail->charges_mensuelles,
                    'montant_paye' => 0,
                    'montant_restant' => $bail->montant_loyer + $bail->charges_mensuelles,
                    'statut' => 'impayé',
                    'date_echeance' => $dateCible->format('Y-m-d'),
                    'periode' => $periodeCible,
                ]);
                $compteur++;
                
                // Bonus : Tu pourrais déclencher un Event ici pour envoyer une Notif Push "Votre nouveau loyer est disponible"
            }
        }

        $this->info("Terminé. {$compteur} factures générées pour la période de {$periodeCible}.");
        Log::info("CRON Luwaas : {$compteur} loyers générés pour le {$dateCible->format('Y-m-d')}.");
    }
}
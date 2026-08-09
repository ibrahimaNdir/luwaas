<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Paiement;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendRentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loyers:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie des rappels SMS automatiques pour les loyers impayés ou à venir';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService)
    {
        $this->info('Démarrage de l\'envoi des rappels de loyer...');

        // Récupérer les paiements impayés dont la date d'échéance est aujourd'hui, demain ou passée
        $paiements = Paiement::with(['bail.logement', 'locataire.user', 'bail.proprietaire'])
            ->where('statut', 'impayé')
            ->whereDate('date_echeance', '<=', Carbon::now()->addDays(2))
            ->get();

        $count = 0;

        foreach ($paiements as $paiement) {
            $proprietaire = $paiement->bail->proprietaire ?? null;
            $locataireUser = $paiement->locataire->user ?? null;

            if (!$proprietaire || !$locataireUser || !$locataireUser->telephone) {
                continue;
            }

            // Vérifier si le propriétaire a accès à la feature
            if ($proprietaire->canUseFeature('Rappels automatiques de loyer par SMS')) {
                
                $montant = number_format($paiement->montant_restant, 0, ',', ' ');
                $mois = Carbon::parse($paiement->date_echeance)->translatedFormat('F Y');
                $dateEcheance = Carbon::parse($paiement->date_echeance);
                
                if ($dateEcheance->isPast()) {
                    $message = "Bonjour {$locataireUser->prenom}, votre loyer de {$montant} FCFA pour {$mois} est en retard. Merci de régulariser la situation.";
                } elseif ($dateEcheance->isToday()) {
                    $message = "Bonjour {$locataireUser->prenom}, n'oubliez pas que votre loyer de {$montant} FCFA pour {$mois} est à régler aujourd'hui.";
                } else {
                    $message = "Bonjour {$locataireUser->prenom}, rappel : votre loyer de {$montant} FCFA pour {$mois} sera dû le {$dateEcheance->format('d/m/Y')}.";
                }

                $envoye = $smsService->send($locataireUser->telephone, $message);

                if ($envoye) {
                    $count++;
                    Log::info("Rappel de loyer envoyé via SMS au locataire {$locataireUser->id} pour le paiement {$paiement->id}");
                }
            }
        }

        $this->info("Terminé. {$count} rappel(s) envoyé(s).");
        return 0;
    }
}

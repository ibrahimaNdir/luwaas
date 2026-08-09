<?php

namespace App\Console\Commands;

use App\Models\Bail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirerBauxNonPayes extends Command
{
    protected $signature = 'baux:expirer-non-payes';
    protected $description = 'Expire les baux en attente de paiement depuis plus de 7 jours et libère le logement';

    public function handle()
    {
        $baux = Bail::where('statut', 'en_attente_paiement')
            ->where('created_at', '<', now()->subDays(7))
            ->get();

        $compteur = 0;

        foreach ($baux as $bail) {
            $bail->update(['statut' => 'expire']);

            $bail->logement->update(['statut_occupe' => 'disponible']);

            if ($bail->demande) {
                $bail->demande->update(['status' => 'expiree']);
            }

            $compteur++;
        }

        $this->info("{$compteur} bail(aux) expiré(s).");
        Log::info("CRON Luwaas : {$compteur} baux expirés pour non-paiement.");
    }
}
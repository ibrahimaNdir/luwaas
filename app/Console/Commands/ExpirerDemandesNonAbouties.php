<?php

namespace App\Console\Commands;

use App\Events\DemandeRappelJ7;
use App\Models\Demande;
use App\Services\DemandeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirerDemandesNonAbouties extends Command
{
    protected $signature = 'demandes:expirer-non-abouties';
    protected $description = 'Rappel à J+7 et clôture en non_aboutie à J+14 les demandes acceptées sans bail';

    public function handle(DemandeService $demandeService)
    {
        $aRappeler = Demande::where('status', 'acceptee')
            ->where('rappel_envoye', false)
            ->where('date_acceptation', '<=', now()->subDays(7))
            ->get();

        foreach ($aRappeler as $demande) {
            event(new DemandeRappelJ7($demande));
            $demande->update(['rappel_envoye' => true]);
        }

        $aExpirer = Demande::where('status', 'acceptee')
            ->where('date_acceptation', '<=', now()->subDays(14))
            ->get();

        foreach ($aExpirer as $demande) {
            $demandeService->marquerNonAboutie($demande);
        }

        $this->info(count($aRappeler) . " rappel(s) envoyé(s), " . count($aExpirer) . " demande(s) clôturée(s) en non_aboutie.");
        Log::info("CRON Luwaas : " . count($aRappeler) . " rappels J+7, " . count($aExpirer) . " demandes non abouties.");
    }
}
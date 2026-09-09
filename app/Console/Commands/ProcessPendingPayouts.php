<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayoutProcessorService;

class ProcessPendingPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'luwaas:process-payouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite tous les versements en attente';

    /**
     * Execute the console command.
     */
    public function handle(PayoutProcessorService $payoutProcessorService)
    {
        $this->info('Début du traitement des versements en attente...');
        $result = $payoutProcessorService->processAllPendingPayouts();

        $this->info("Traitement terminé :");
        $this->info("- Total traité : {$result['total_processed']}");
        $this->info("- Succès : {$result['success_count']}");
        $this->info("- Échecs : {$result['fail_count']}");

        if ($result['fail_count'] > 0) {
            $this->error("Des échecs ont été détectés - vérifiez les logs pour plus de détails");
        } else {
            $this->info("Tous les versements ont été traités avec succès");
        }
    }
}

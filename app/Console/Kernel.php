<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('luwaas:rappel-loyers')
            ->dailyAt('06:00')
            ->timezone('Africa/Dakar');


        $schedule->command('luwaas:rappel-fin-bail')
            ->dailyAt('08:00')
            ->timezone('Africa/Dakar');

        // ✅ NOUVELLE LIGNE : Rappel retards (10h)
        $schedule->command('luwaas:rappel-retards')
            ->dailyAt('10:00')
            ->timezone('Africa/Dakar');

        // ✅ NOUVELLE LIGNE : Rappels automatiques SMS (Plan Pro) (11h)
        $schedule->command('loyers:remind')
            ->dailyAt('11:00')
            ->timezone('Africa/Dakar');

        $schedule->command('luwaas:rappel-debut-bail')
            ->dailyAt('00:00')
            ->timezone('Africa/Dakar');


        $schedule->command('subscriptions:expire')
            ->dailyAt('00:30')
            ->timezone('Africa/Dakar');
        $schedule->command('transactions:nettoyer')
            ->hourly();
        $schedule->command('baux:expirer-non-payes')
            ->dailyAt('01:00')
            ->timezone('Africa/Dakar');
        $schedule->command('demandes:expirer-non-abouties')
            ->dailyAt('09:00')
            ->timezone('Africa/Dakar');

        // Traitement périodique des versements en attente (sauvegarde pour les échecs instantanés)
        $schedule->command('luwaas:process-payouts')
            ->everyFiveMinutes()
            ->timezone('Africa/Dakar')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

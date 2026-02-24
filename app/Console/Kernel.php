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

        $schedule->command('luwaas:rappel-debut-bail')
            ->dailyAt('00:00')
            ->timezone('Africa/Dakar');
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

<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait Loggable
{
    /**
     * Log une information uniquement si on est en développement,
     * ou si c'est une erreur critique.
     */
    protected function logDev(string $level, string $message, array $context = []): void
    {
        // En prod, on ignore les niveaux 'info' et 'debug'
        if (config('app.env') === 'production' && in_array($level, ['info', 'debug'])) {
            return;
        }

        Log::log($level, $message, $context);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class NettoyerTransactionsExpirees extends Command
{
    protected $signature = 'transactions:nettoyer';
    protected $description = 'Marque comme rejetées les transactions en attente dont le délai a expiré';

    public function handle()
    {
        $count = Transaction::where('statut', 'en_attente')
            ->where('expire_at', '<', now())
            ->update(['statut' => 'rejete']);

        $this->info("{$count} transaction(s) expirée(s) nettoyée(s).");
    }
}
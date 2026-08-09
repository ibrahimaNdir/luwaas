<?php

namespace App\Events;

use App\Models\Demande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DemandeRappelJ7
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Demande $demande) {}
}
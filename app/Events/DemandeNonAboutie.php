<?php

namespace App\Events;

use App\Models\Demande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DemandeNonAboutie
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Demande $demande;

    /**
     * Create a new event instance.
     */
    public function __construct(Demande $demande)
    {
        $this->demande = $demande;
    }
}
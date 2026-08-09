<?php

namespace App\Events;

use App\Models\Demande;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DemandeLogementRecue
{
    use Dispatchable, SerializesModels;

    public $demande;

    /**
     * Quand une nouvelle demande de logement est créée
     */
    public function __construct(Demande $demande)
    {
        $this->demande = $demande;
    }
}

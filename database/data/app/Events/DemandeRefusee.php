<?php

namespace App\Events;

use App\Models\Demande;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DemandeRefusee
{
    use Dispatchable, SerializesModels;

    public $demande;

    /**
     * Quand un bailleur refuse une demande
     */
    public function __construct(Demande $demande)
    {
        $this->demande = $demande;
    }
}

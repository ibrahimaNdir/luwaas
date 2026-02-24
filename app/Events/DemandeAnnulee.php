<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Demande;

class DemandeAnnulee
{
    use Dispatchable, SerializesModels;

    public $demande;
    public $ancienStatus; 

    /**
     * Quand une demande de logement est annulée par le locataire
     */
    public function __construct(Demande $demande, string $ancienStatus) 
    {
        $this->demande = $demande;
        $this->ancienStatus = $ancienStatus; 
    }
}

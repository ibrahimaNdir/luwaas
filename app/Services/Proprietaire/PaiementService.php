<?php

namespace App\Services\Proprietaire;

use App\Models\Paiement;

class PaiementService
{
    public function index()
    {
        return Paiement::all();
    }

    public function indexByBail($bailId)
    {
        return  Paiement::where('bail_id', $bailId)->get();
    }

}

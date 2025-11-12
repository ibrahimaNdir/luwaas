<?php

namespace App\Services\Proprietaire;

use App\Models\Bail;

class BailService
{
    public function index()
    {
        return Bail::all();
    }

    public function indexByPaiement($proprieteId)
    {
        return Bail::where('locataire_id', $proprieteId)->get();
    }

    public function show($id)
    {
        return Bail::find($id);
    }


}

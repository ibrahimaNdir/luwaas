<?php

namespace App\Services\Proprietaire;

use App\Models\Bail;

class BailService
{
    public function index()
    {
        return Bail::all();
    }


}

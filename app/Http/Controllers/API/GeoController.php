<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Departement;
use App\Models\Region;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    /**
     * Retourne toutes les régions
     */
    public function regions()
    {
        return response()->json(Region::all());
    }

    /**
     * Retourne les départements d'une région donnée
     */
    public function departements($regionId)
    {
        $departements = Departement::where('region_id', $regionId)->get();
        return response()->json($departements);
    }

    /**
     * Retourne les communes d'un département donné
     */
    public function communes($departementId)
    {
        $communes = Commune::where('departement_id', $departementId)->get();
        return response()->json($communes);
    }
}

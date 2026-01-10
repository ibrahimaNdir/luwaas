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
        return response()->json(
            Region::select('id', 'nom')->orderBy('nom')->get()
        );
    }

    /**
     * Retourne les départements d'une région donnée
     */
    public function departements($regionId)
    {
        $region = Region::find($regionId);

        if (!$region) {
            return response()->json(['error' => 'Région introuvable'], 404);
        }

        return response()->json(
            Departement::select('id', 'nom')
                ->where('region_id', $regionId)
                ->orderBy('nom')
                ->get()
        );
    }

    /**
     * Retourne les communes d'un département donné
     */
    public function communes($departementId)
    {
        $dep = Departement::find($departementId);

        if (!$dep) {
            return response()->json(['error' => 'Département introuvable'], 404);
        }

        return response()->json(
            Commune::select('id', 'nom')
                ->where('departement_id', $departementId)
                ->orderBy('nom')
                ->get()
        );
    }
}


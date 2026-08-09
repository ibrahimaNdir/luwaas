<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
// app/Http/Controllers/DashboardController.php

use App\Services\ProprietaireDashboardService;
use App\Http\Resources\ProprietaireDashboardResource;
use Illuminate\Http\Request;

class ProprietaireDashboardController extends Controller
{
    public function __construct(
        private ProprietaireDashboardService $dashboardService,
    ) {}

    /**
     * GET /proprietaire/dashboard
     */
    public function proprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;
        
        // ✅ Appelle le service
        $data = $this->dashboardService->dashboard($proprietaireId);
        
        // ✅ Retourne via Resource
        return new ProprietaireDashboardResource($data);
    }

    /**
     * GET /proprietaire/stats/historique-6-mois
     * (Optionnel si tu veux séparer les endpoints)
     */
    public function historique6Mois(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;
        $data = $this->dashboardService->historique6Mois($proprietaireId);
        
        return response()->json($data);
    }

    /**
     * GET /proprietaire/stats/par-propriete
     */
    public function statsParPropriete(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;
        $data = $this->dashboardService->statsParPropriete($proprietaireId);
        
        return response()->json($data);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProprieteRequest;
use App\Http\Resources\ProprieteResource;
use App\Models\Propriete;
use App\Services\Proprietaire\PropertyService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    // ✅ Injection de dépendances au lieu de new PropertyService()
    public function __construct(protected PropertyService $propertyService) {}

    // ═══════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════

    public function index()
    {
        return ProprieteResource::collection(
            $this->propertyService->index()
        );
    }

    public function store(ProprieteRequest $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $propriete = $this->propertyService->creerPropriete(
                $request->validated(),
                $proprietaireId
            );

            return response()->json([
                'message'   => 'Propriété ajoutée avec succès.',
                'propriete' => new ProprieteResource($propriete),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création : ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $proprietaireId = $this->proprietaireId(request());

        $propriete = Propriete::where('id', $id)
            ->where('proprietaire_id', $proprietaireId)
            ->firstOrFail();

        $propriete->delete();

        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════
    // LISTING & RECHERCHE
    // ═══════════════════════════════════════════

    public function allProperty(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return ProprieteResource::collection(
            $this->propertyService->indexByOwner($proprietaireId)
        );
    }

    public function search(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        $results = $this->propertyService->search(
            $request->only(['region_id', 'type']),
            $proprietaireId
        );

        return response()->json($results);
    }

    public function countProperty(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return response()->json([
            'total_proprietes' => $this->propertyService->countByOwner($proprietaireId)
        ]);
    }

    // ═══════════════════════════════════════════
    // DASHBOARD & STATS
    // ═══════════════════════════════════════════

    public function dashboard(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return response()->json([
            'dashboard'         => $this->propertyService->dashboard($proprietaireId),
            'historique_6_mois' => $this->propertyService->historique6Mois($proprietaireId),
        ]);
    }

    public function statsProprietes(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return response()->json([
            'proprietes' => $this->propertyService->statsParPropriete($proprietaireId)
        ]);
    }

    // ═══════════════════════════════════════════
    // HELPER PRIVÉ
    // ═══════════════════════════════════════════

    private function proprietaireId(Request $request): int
    {
        $id = $request->user()->proprietaire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }
}
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
    protected $propertyService;
    /**
     * OffreController constructor.
     */
    public function __construct()
    {
        $this->propertyService = new PropertyService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offres =  $this->propertyService->index();

        return ProprieteResource::collection($offres);
        //
    }

    /**
     * Store a newly created resource in storage.
     */
       public function store(ProprieteRequest $request)
    {
        $proprietaire = auth()->user()->proprietaire;

        if (!$proprietaire) {
            return response()->json(['message' => 'Utilisateur non lié à un compte propriétaire.'], 403);
        }

        try {
            $data = $request->validated();
            $data['proprietaire_id'] = $proprietaire->id;

            $propriete = Propriete::create($data);

            // Modification ici : on utilise la Resource pour formater la réponse
            return response()->json([
                'message' => 'Propriété ajoutée avec succès',
                'propriete' => new ProprieteResource($propriete)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la propriété : ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Propriete::destroy($id);
        return response()->json("",204);
    }
    public function countProperty(Request $request)
    {
        $ownerId = $request->user()->proprietaire->id;
        $count = $this->propertyService->countByOwner($ownerId);

        return response()->json(['total_proprietes' => $count], 200);
    }



       public function dashboard(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        if (!$proprietaire) {
            return response()->json(['message' => 'Bailleur non trouvé'], 404);
        }

        // ✅ Stats principales (temps réel + mois en cours)
        $dashboard = $this->propertyService->dashboard($proprietaire->id);

        // ✅ Historique 6 mois
        $historique = $this->propertyService->historique6Mois($proprietaire->id);

        return response()->json([
            'dashboard' => $dashboard,
            'historique_6_mois' => $historique,
        ], 200);
    }

    /**
     * Stats détaillées par propriété (optionnel)
     */
    public function statsProprietes(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        if (!$proprietaire) {
            return response()->json(['message' => 'Bailleur non trouvé'], 404);
        }

        $stats = $this->propertyService->statsParPropriete($proprietaire->id);

        return response()->json(['proprietes' => $stats], 200);
    }


    // ✅ Rechercher / filtrer les propriétés d’un propriétaire
    public function search(Request $request)
    {
        $ownerId = $request->user()->proprietaire->id;

        $filters = $request->only(['region_id', 'type']);
        $results = $this->propertyService->search($filters, $ownerId);

        return response()->json($results, 200);
    }

    // ✅ Lister les propriétés d’un propriétaire
    public function allProperty(Request $request)
    {
        $ownerId = $request->user()->proprietaire->id;

        $proprietes = $this->propertyService->indexByOwner($ownerId);

        return ProprieteResource::collection($proprietes);
    }




}

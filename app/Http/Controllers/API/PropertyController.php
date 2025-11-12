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

            return response()->json([
                'message' => 'Propriété ajoutée avec succès',
                'propriete' => $propriete
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



    // ✅ Dashboard du propriétaire connecté
    public function dashboard(Request $request)
    {
        $ownerId = $request->user()->proprietaire->id;
        $stats = $this->propertyService->dashboard($ownerId);

        return response()->json($stats, 200);

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

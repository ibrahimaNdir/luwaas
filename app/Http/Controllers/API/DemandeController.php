<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeLocataireResource;
use App\Http\Resources\DemandeProprietaireResource;
use App\Models\Demande;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Récupérer le locataire connecté
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé ou pas de profil locataire.'], 403);
        }

        // Vérifier les infos du logement et du bailleur seulement !
        $validated = $request->validate([
            'logement_id'     => 'required|exists:logements,id',
            'proprietaire_id' => 'required|exists:proprietaires,id',
        ]);

        $demande = Demande::create([
            'logement_id'     => $validated['logement_id'],
            'locataire_id'    => $locataire_id, // assigné automatiquement
            'proprietaire_id' => $validated['proprietaire_id'],
            'date_demande'    => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande créée avec succès.',
            'demande' => $demande
        ], 201);
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
    public function destroy(string $id)
    {
        //
    }

    public function demandesLocataire(Request $request)
    {
        $user = $request->user();
        // Assurant que l'utilisateur a bien le rôle 'locataire'
        // et que locataire_id soit lié à ce user (adapte selon ta structure)
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé ou pas de profil locataire.'], 403);
        }

        $demandes = Demande::with(['logement', 'proprietaire'])
            ->where('locataire_id', $locataire_id)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeLocataireResource::collection($demandes);
    }

    public function demandesProprietaire(Request $request)
    {
        $user = $request->user();
        // Assurant que l'utilisateur a bien le rôle 'propriétaire'
        $proprietaire_id = $user->proprietaire->id ?? null;

        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé ou pas de profil propriétaire.'], 403);
        }

        $demandes = Demande::with(['logement', 'locataire'])
            ->where('proprietaire_id', $proprietaire_id)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeProprietaireResource::collection($demandes);
    }


}

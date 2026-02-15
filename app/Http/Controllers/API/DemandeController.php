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
    public function store(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // 1. Validation
        $validated = $request->validate([
            'logement_id' => 'required|exists:logements,id',
        ]);

        // 2. Recherche Propriétaire
        $logement = \App\Models\Logement::with('propriete.proprietaire')->findOrFail($validated['logement_id']);

        if (!$logement->propriete || !$logement->propriete->proprietaire) {
            return response()->json(['message' => 'Impossible de trouver le propriétaire.'], 500);
        }

        $proprietaire_id = $logement->propriete->proprietaire->id;

        // 3. Vérification Doublon
        $existeDeja = \App\Models\Demande::where('logement_id', $logement->id)
            ->where('locataire_id', $locataire_id)
            ->where('status', '!=', 'annulee')
            ->exists();

        if ($existeDeja) {
            return response()->json(['message' => 'Vous avez déjà une demande en cours pour ce logement.'], 409);
        }

        // 4. Création de la demande
        $demande = \App\Models\Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire_id,
            'proprietaire_id' => $proprietaire_id,
            'date_demande'    => now(),
            'status'          => 'en_attente'
        ]);

        // ✅ 5. DISPATCH EVENT (Remplace toute la logique de notification)
        event(new \App\Events\DemandeLogementRecue($demande));

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée au propriétaire.',
            'demande' => $demande
        ], 201);
    }


    /**
     * ✅ ACCEPTER LA DEMANDE (Côté Propriétaire)
     * Cela signifie : "OK pour une visite"
     */
    public function accepter($id, Request $request)
    {
        $demande = \App\Models\Demande::findOrFail($id);
        $user = $request->user();

        // Sécurité : Vérifier que c'est bien le propriétaire
        if ($demande->proprietaire_id !== $user->proprietaire->id) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Mise à jour du statut
        $demande->update(['status' => 'acceptee']);

        // ✅ DISPATCH EVENT (Remplace toute la logique de notification)
        event(new \App\Events\DemandeAcceptee($demande));

        return response()->json(['message' => 'Demande acceptée. Le locataire a été notifié.']);
    }



    /**
     * ❌ REFUSER LA DEMANDE (Côté Propriétaire)
     */
    public function refuser($id, Request $request)
    {
        $demande = \App\Models\Demande::findOrFail($id);
        $user = $request->user();

        // Sécurité
        if ($demande->proprietaire_id !== $user->proprietaire->id) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Mise à jour du statut
        $demande->update(['status' => 'refusee']);

        // ✅ DISPATCH EVENT (Remplace toute la logique de notification)
        event(new \App\Events\DemandeRefusee($demande));

        return response()->json(['message' => 'Demande refusée.']);
    }


    // ... Tes méthodes existantes (demandesLocataire, demandesProprietaire) ...
    public function demandesLocataire(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        $demandes = Demande::with(['logement', 'proprietaire'])
            ->where('locataire_id', $locataire_id)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeLocataireResource::collection($demandes);
    }

    public function demandesProprietaire(Request $request)
    {
        $user = $request->user();
        $proprietaire_id = $user->proprietaire->id ?? null;

        $demandes = Demande::with(['logement', 'locataire'])
            ->where('proprietaire_id', $proprietaire_id)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeProprietaireResource::collection($demandes);
    }
}

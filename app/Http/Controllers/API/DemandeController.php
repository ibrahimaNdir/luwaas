<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeLocataireResource;
use App\Http\Resources\DemandeProprietaireResource;
use App\Models\Demande;
use Illuminate\Http\Request;
use App\Models\Logement;



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
    /**
     * 🗑️ SUPPRIMER UNE DEMANDE (Côté Locataire)
     * Uniquement si status = 'refusee' ou 'annulee'
     */
    public function destroy(string $id, Request $request)
    {
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        $locataire_id = $user->locataire->id ?? null;

        // Sécurité : seul le locataire de la demande peut supprimer
        if (!$locataire_id || $demande->locataire_id !== $locataire_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // On ne peut supprimer que les demandes terminées
        if (!in_array($demande->status, ['refusee', 'annulee'])) {
            return response()->json([
                'message' => 'Impossible de supprimer une demande active. Annulez-la d\'abord.'
            ], 422);
        }

        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée avec succès.'
        ]);
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

        // 2. Recherche logement + propriétaire
        $logement = Logement::with('propriete.proprietaire')
            ->findOrFail($validated['logement_id']);

        if (!$logement->propriete || !$logement->propriete->proprietaire) {
            return response()->json([
                'message' => 'Impossible de trouver le propriétaire.'
            ], 500);
        }

        // ✅ 3. Vérification statut logement
        if ($logement->status !== 'disponible') {
            return response()->json([
                'message' => 'Ce logement n\'est plus disponible à la location.'
            ], 422);
        }

        // 4. Vérification doublon
        $existeDeja = Demande::where('logement_id', $logement->id)
            ->where('locataire_id', $locataire_id)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->exists();

        if ($existeDeja) {
            return response()->json([
                'message' => 'Vous avez déjà une demande en cours pour ce logement.'
            ], 409);
        }

        // 5. Création de la demande
        $proprietaire_id = $logement->propriete->proprietaire->id;

        $demande = Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire_id,
            'proprietaire_id' => $proprietaire_id,
            'date_demande'    => now(),
            'status'          => 'en_attente',
        ]);

        // 6. Notification
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
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        // ✅ Null check
        $proprietaire_id = $user->proprietaire->id ?? null;
        if (!$proprietaire_id || $demande->proprietaire_id !== $proprietaire_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // ✅ Vérification statut
        if ($demande->status !== 'en_attente') {
            return response()->json([
                'message' => 'Cette demande ne peut plus être acceptée.'
            ], 400);
        }

        $demande->update(['status' => 'acceptee']);

        event(new \App\Events\DemandeAcceptee($demande));

        return response()->json([
            'success' => true,
            'message' => 'Demande acceptée. Le locataire a été notifié.',
            'demande' => $demande
        ]);
    }



    /**
     * ❌ REFUSER LA DEMANDE (Côté Propriétaire)
     */
    public function refuser($id, Request $request)
    {
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        // ✅ Null check
        $proprietaire_id = $user->proprietaire->id ?? null;
        if (!$proprietaire_id || $demande->proprietaire_id !== $proprietaire_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // ✅ Vérification statut
        if ($demande->status !== 'en_attente') {
            return response()->json([
                'message' => 'Cette demande ne peut plus être refusée.'
            ], 400);
        }

        $demande->update(['status' => 'refusee']);

        event(new \App\Events\DemandeRefusee($demande));

        return response()->json([
            'success' => true,
            'message' => 'Demande refusée.',
            'demande' => $demande
        ]);
    }


    // ... Tes méthodes existantes (demandesLocataire, demandesProprietaire) ...
    public function demandesLocataire(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        // ✅ Null check ajouté
        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
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
        $proprietaire_id = $user->proprietaire->id ?? null;

        $demandes = Demande::with(['logement', 'locataire'])
            ->where('proprietaire_id', $proprietaire_id)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeProprietaireResource::collection($demandes);
    }

    /**
     * 🚫 ANNULER UNE DEMANDE (Côté Locataire)
     * Possible si status = 'en_attente' ou 'acceptee'
     */
    public function annuler(string $id, Request $request)
    {
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        $locataire_id = $user->locataire->id ?? null;

        // Sécurité : seul le locataire de la demande peut annuler
        if (!$locataire_id || $demande->locataire_id !== $locataire_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // Vérifier que la demande est annulable
        if (!in_array($demande->status, ['en_attente', 'acceptee'])) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être annulée.'
            ], 422);
        }

        // Garder l'ancien statut pour la notification
        $ancienStatus = $demande->status;

        // Mise à jour du statut
        $demande->update(['status' => 'annulee']);

        // Notifier le bailleur
        event(new \App\Events\DemandeAnnulee($demande, $ancienStatus));

        return response()->json([
            'success' => true,
            'message' => 'Demande annulée avec succès.'
        ]);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeLocataireResource;
use App\Http\Resources\DemandeProprietaireResource;
use App\Models\Demande;
use Illuminate\Http\Request;
use App\Services\NotificationService; // <--- AJOUTER
use App\Models\Proprietaire;          // <--- AJOUTER


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
    public function store(Request $request, NotificationService $notifService)
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
        $proprietaire_user = $logement->propriete->proprietaire->user;

        // 3. Vérification Doublon
        $existeDeja = Demande::where('logement_id', $logement->id)
            ->where('locataire_id', $locataire_id)
            ->where('status', '!=', 'annulee')
            ->exists();

        if ($existeDeja) {
            return response()->json(['message' => 'Vous avez déjà une demande en cours pour ce logement.'], 409);
        }

        // 4. Création de la demande
        $demande = Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire_id,
            'proprietaire_id' => $proprietaire_id,
            'date_demande'    => now(),
            'status'          => 'en_attente'
        ]);

        // 5. Notification AVEC NOM DU LOCATAIRE (C'est ici la modif !)
        // 5. Notification AVEC TOUTES LES INFOS NÉCESSAIRES
        // 5. Notification AVEC TOUTES LES INFOS NÉCESSAIRES
        if ($proprietaire_user) {

            // On construit proprement le nom complet
            $nomComplet = ucfirst($user->prenom) . ' ' . ucfirst($user->nom);

            // Capitalise le type de logement
            $typelogement = ucfirst($logement->typelogement);

            // ✅ UTILISE $typelogement ici (pas $logement->typelogement)
            $message = "{$nomComplet} souhaite visiter votre {$typelogement} {$logement->numero} - {$logement->propriete->titre}.";

            $notifService->sendToUser(
                $proprietaire_user,
                "Nouvelle demande !",
                $message,
                "nouvelle_demande",
                [
                    'demande_id' => (string)$demande->id,
                    'logement_id' => (string)$logement->id,
                    'logement_numero' => $logement->numero,
                    'logement_type' => $typelogement,  // ✅ Tu peux aussi l'ajouter dans les données
                    'propriete_nom' => $logement->propriete->titre,
                    'locataire_id' => (string)$locataire_id,
                    'locataire_nom' => $nomComplet,
                    'locataire_telephone' => $user->telephone ?? 'Non renseigné'
                ]
            );
        }



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
    public function accepter($id, Request $request, NotificationService $notifService)
    {
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        // Sécurité : Vérifier que c'est bien le propriétaire de la demande qui clique
        if ($demande->proprietaire_id !== $user->proprietaire->id) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Mise à jour du statut
        $demande->update(['status' => 'acceptee']);

        //  Notification au LOCATAIRE
        // "Votre demande a été acceptée, préparez-vous pour la visite !"
        if ($demande->locataire && $demande->locataire->user) {
            $notifService->sendToUser(
                $demande->locataire->user,
                "Demande acceptée ! ✅",
                "Le propriétaire a accepté votre demande. Vous pouvez maintenant organiser une visite.",
                "demande_acceptee" // Ce type permettra de rediriger vers l'écran détail
            );
        }

        return response()->json(['message' => 'Demande acceptée. Le locataire a été notifié.']);
    }


    /**
     * ❌ REFUSER LA DEMANDE (Côté Propriétaire)
     */
    public function refuser($id, Request $request, NotificationService $notifService)
    {
        $demande = Demande::findOrFail($id);
        $user = $request->user();

        if ($demande->proprietaire_id !== $user->proprietaire->id) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $demande->update(['status' => 'refusee']);

        // Notif au LOCATAIRE
        if ($demande->locataire && $demande->locataire->user) {
            $notifService->sendToUser(
                $demande->locataire->user,
                "Demande refusée ❌",
                "Le propriétaire n'a pas donné suite à votre demande pour le moment.",
                "demande_refusee"
            );
        }

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

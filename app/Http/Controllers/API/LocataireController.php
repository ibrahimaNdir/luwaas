<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Locataire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LocataireController extends Controller
{
    // ─────────────────────────────────────────
    // 1. LISTE DES LOCATAIRES DU PROPRIÉTAIRE
    // ─────────────────────────────────────────

    public function index(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        $locataires = Locataire::whereHas('baux', function ($query) use ($proprietaire) {
                            $query->whereHas('logement', function ($q) use ($proprietaire) {
                                $q->where('proprietaire_id', $proprietaire->id);
                            });
                        })
                        ->with(['user', 'baux.logement'])
                        ->get();

        return response()->json([
            'message'    => 'Liste des locataires',
            'locataires' => $locataires,
            'total'      => $locataires->count(),
            'limits'     => $proprietaire->planLimits(), // ✅ quotas pour le front
        ]);
    }

    // ─────────────────────────────────────────
    // 2. CRÉER UN LOCATAIRE
    // ─────────────────────────────────────────

    public function store(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        // ✅ Feature-gating : vérifier la limite du plan
        if (! $proprietaire->canAddLocataire()) {
            return response()->json([
                'message'     => "Vous avez atteint la limite de locataires de votre plan {$proprietaire->plan}.",
                'code'        => 'LOCATAIRE_LIMIT_REACHED',
                'upgrade_url' => url('/plans'),
                'limits'      => $proprietaire->planLimits(),
            ], 403);
        }

        $request->validate([
            'prenom'    => 'required|string|max:255',
            'nom'       => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
        ]);

        try {
            DB::beginTransaction();

            // Créer le compte User du locataire
            $user = User::create([
                'prenom'    => $request->prenom,
                'nom'       => $request->nom,
                'email'     => $request->email,
                'telephone' => $request->telephone,
                'password'  => Hash::make($request->telephone), // mot de passe par défaut = téléphone
                'user_type' => 'locataire',
            ]);

            // Créer le profil Locataire
            $locataire = Locataire::create([
                'user_id'      => $user->id,
                'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            ]);

            DB::commit();

            return response()->json([
                'message'   => 'Locataire créé avec succès.',
                'locataire' => $locataire->load('user'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────
    // 3. VOIR UN LOCATAIRE
    // ─────────────────────────────────────────

    public function show(Request $request, string $id)
    {
        $proprietaire = $request->user()->proprietaire;

        $locataire = Locataire::whereHas('baux', function ($query) use ($proprietaire) {
                            $query->whereHas('logement', function ($q) use ($proprietaire) {
                                $q->where('proprietaire_id', $proprietaire->id);
                            });
                        })
                        ->with(['user', 'baux.logement'])
                        ->findOrFail($id);

        return response()->json([
            'message'   => 'Détails du locataire',
            'locataire' => $locataire,
        ]);
    }

    // ─────────────────────────────────────────
    // 4. MODIFIER UN LOCATAIRE
    // ─────────────────────────────────────────

    public function update(Request $request, string $id)
    {
        $proprietaire = $request->user()->proprietaire;

        $locataire = Locataire::whereHas('baux', function ($query) use ($proprietaire) {
                            $query->whereHas('logement', function ($q) use ($proprietaire) {
                                $q->where('proprietaire_id', $proprietaire->id);
                            });
                        })
                        ->with('user')
                        ->findOrFail($id);

        $request->validate([
            'prenom'    => 'sometimes|string|max:255',
            'nom'       => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $locataire->user->id,
            'telephone' => 'sometimes|string|unique:users,telephone,' . $locataire->user->id,
        ]);

        $locataire->user->update($request->only(['prenom', 'nom', 'email', 'telephone']));

        return response()->json([
            'message'   => 'Locataire mis à jour.',
            'locataire' => $locataire->load('user'),
        ]);
    }

    // ─────────────────────────────────────────
    // 5. SUPPRIMER UN LOCATAIRE
    // ─────────────────────────────────────────

    public function destroy(Request $request, string $id)
    {
        $proprietaire = $request->user()->proprietaire;

        $locataire = Locataire::whereHas('baux', function ($query) use ($proprietaire) {
                            $query->whereHas('logement', function ($q) use ($proprietaire) {
                                $q->where('proprietaire_id', $proprietaire->id);
                            });
                        })
                        ->findOrFail($id);

        // Vérifier si le locataire a un bail actif
        $bailActif = $locataire->baux()
                               ->where('statut', 'actif')
                               ->exists();

        if ($bailActif) {
            return response()->json([
                'message' => 'Impossible de supprimer un locataire avec un bail actif.',
                'code'    => 'BAIL_ACTIF',
            ], 422);
        }

        $locataire->user->delete(); // cascade → supprime aussi le locataire

        return response()->json([
            'message' => 'Locataire supprimé avec succès.',
        ]);
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Locataire;
use App\Models\Proprietaire;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreLocataireRequest;
use App\Http\Requests\StoreProprietaireRequest;

class UserController extends Controller
{
    /**
     * Permet à un utilisateur propriétaire d'ajouter un profil locataire
     * @param  \App\Http\Requests\StoreLocataireRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addLocataireProfile(StoreLocataireRequest $request)
    {
        $user = $request->user();

        // Empêcher la création du profil s'il existe déjà
        if ($user->locataire) {
            return response()->json([
                'message' => 'Vous avez déjà un profil locataire.'
            ], 422);
        }

        try {
            // Créer le profil locataire lié à cet utilisateur
            $user->locataire()->create([
                // Pré-remplir certains champs depuis l'utilisateur si nécessaire
                'telephone' => $user->telephone,
                // D'autres champs peuvent être ajoutés ici selon vos besoins
            ]);

            Log::info("Profil locataire ajouté pour l'utilisateur {$user->id}");

            return response()->json([
                'message' => 'Profil locataire ajouté avec succès. Vous pouvez maintenant chercher des logements.'
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'ajout du profil locataire: " . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de l\'ajout du profil locataire. Veuillez réessayer.'
            ], 500);
        }
    }

    /**
     * Permet à un utilisateur locataire d'ajouter un profil propriétaire
     * @param  \App\Http\Requests\StoreProprietaireRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addProprietaireProfile(StoreProprietaireRequest $request)
    {
        $user = $request->user();

        // Empêcher la création du profil s'il existe déjà
        if ($user->proprietaire) {
            return response()->json([
                'message' => 'Vous avez déjà un profil propriétaire.'
            ], 422);
        }

        try {
            // Créer le profil propriétaire lié à cet utilisateur
            $user->proprietaire()->create([
                // Pré-remplir certains champs depuis l'utilisateur si nécessaire
                'telephone' => $user->telephone,
                // D'autres champs peuvent être ajoutés ici selon vos besoins
            ]);

            Log::info("Propriétaire profil ajouté pour l'utilisateur {$user->id}");

            return response()->json([
                'message' => 'Profil propriétaire ajouté avec succès. Vous pouvez maintenant gérer vos biens.'
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'ajout du profil propriétaire: " . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de l\'ajout du profil propriétaire. Veuillez réessayer.'
            ], 500);
        }
    }
}
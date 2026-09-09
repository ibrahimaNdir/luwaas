<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locataire;
use Illuminate\Http\Request;

class AdminLocataireController extends Controller
{
    /**
     * Suspendre un compte locataire (is_actif = false)
     */
    public function suspend(string $id, Request $request)
    {
        $locataire = Locataire::findOrFail($id);

        // Optional: prevent suspending an admin if locataire is linked to a user with admin role
        if ($locataire->user && $locataire->user->user_type === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de suspendre un compte administrateur.',
            ], 403);
        }

        $locataire->update(['is_actif' => false]);

        \Log::info("Locataire {$locataire->id} suspendu par admin {$request->user()->id ?? 'unknown'}");

        return response()->json([
            'success' => true,
            'message' => 'Compte locataire suspendu avec succès.',
            'data'    => $locataire,
        ]);
    }

    /**
     * Réactiver un compte locataire (is_actif = true)
     */
    public function activate(string $id, Request $request)
    {
        $locataire = Locataire::findOrFail($id);

        $locataire->update(['is_actif' => true]);

        \Log::info("Locataire {$locataire->id} réactivé par admin {$request->user()->id ?? 'unknown'}");

        return response()->json([
            'success' => true,
            'message' => 'Compte locataire réactivé avec succès.',
            'data'    => $locataire,
        ]);
    }
}
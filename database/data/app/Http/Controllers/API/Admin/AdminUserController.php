<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::when($request->user_type, fn($q, $type) => $q->where('user_type', $type))
            ->when($request->is_active !== null, function ($q) use ($request) {
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->search, function ($q, $search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function show(string $id)
    {
        $user = User::with(['proprietaire', 'locataire', 'admin'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $user]);
    }

    /**
     * Se connecter en tant qu'un utilisateur (Impersonation pour le support admin)
     */
    public function impersonate(string $id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($user->user_type === 'admin') {
            return response()->json(['message' => 'Impossible d\'usurper un autre administrateur.'], 403);
        }

        // Créer un token Sanctum temporaire
        $token = $user->createToken('impersonated_by_admin_' . $request->user()->id)->plainTextToken;

        \Illuminate\Support\Facades\Log::warning("⚠️ Admin ID {$request->user()->id} s'est connecté en tant que User ID {$user->id} ({$user->email})");

        return response()->json([
            'success' => true,
            'message' => "Connexion réussie en tant que {$user->prenom} {$user->nom}.",
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ]
        ]);
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $data = $this->authService->login($request->only('login', 'password'));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur de connexion : ' . $e->getMessage()
            ], 401);
        }

        return response()->json([
            'message'  => 'Connexion réussie',
            'user'     => new UserResource($data['user']),
            'token'    => $data['token'],
            'firebase_token' => $data['firebase_token'],  // ✅ AJOUTÉ
            'redirect' => $data['redirect'],
        ]);
    }

    public function register(Request $request)
    {
        // 1. Validation des champs communs
        $request->validate([
            'prenom'     => 'required|string|max:255',
            'nom'        => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|unique:users,telephone',
            'password'   => 'required|string|min:6',
            'user_type'  => 'required|in:proprietaire,locataire',
        ]);

        // 2. Validation spécifique pour propriétaire
        if ($request->user_type === 'proprietaire') {
            $request->validate([
                'cni' => 'required|string|unique:proprietaires,cni',
            ]);
        }

        try {
            DB::beginTransaction();

            // 3. Utilise le service pour créer l'utilisateur et générer le firebase token
            $data = $this->authService->register([
                'prenom'    => $request->prenom,
                'nom'       => $request->nom,
                'email'     => $request->email,
                'telephone' => $request->telephone,
                'password'  => $request->password,
                'user_type' => $request->user_type,
            ]);

            $user = $data['user'];

            // 4. Création du profil spécifique
            if ($request->user_type === 'proprietaire') {
                $profil = Proprietaire::create([
                    'user_id'         => $user->id,
                    'cni'             => $request->cni,
                    'proprietaire_id' => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                ]);
            } else {
                $profil = Locataire::create([
                    'user_id'      => $user->id,
                    'cni'          => $request->cni,
                    'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                ]);
            }

            if (!$profil) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Erreur lors de la création du profil spécifique.'
                ], 500);
            }

            DB::commit();

            // 5. Réponse JSON réussie
            return response()->json([
                'message'  => 'Inscription réussie',
                'user'     => new UserResource($user),
                'token'    => $data['token'],
                'firebase_token' => $data['firebase_token'],  // ✅ AJOUTÉ
                'redirect' => $data['redirect'],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'inscription : ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function index()
    {
        $offres = $this->authService->index();
        return response()->json($offres, 200);
    }
}

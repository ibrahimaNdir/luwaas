<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected $firebaseAuth;

    // ✅ Ajoute le constructeur
    public function __construct(FirebaseAuthService $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    public function index()
    {
        return User::all();
    }

    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['login'])
            ->orWhere('telephone', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Votre compte est désactivé.'],
            ]);
        }

        // Supprimer anciens tokens si besoin
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // ✅ AJOUT: Créer le Custom Token Firebase
        $firebaseToken = $this->firebaseAuth->createCustomToken(
            $user->id,
            [
                'email' => $user->email,
                'user_type' => $user->user_type,
                'telephone' => $user->telephone,
            ]
        );

        return [
            'user' => $user,
            'token' => $token,
            'firebase_token' => $firebaseToken,  // ✅ AJOUT
            'redirect' => $this->getRedirectPath($user),
        ];
    }

    public function register(array $data)
    {
        $user = User::create([
            'prenom'     => $data['prenom'],
            'nom'        => $data['nom'],
            'email'      => $data['email'],
            'telephone'  => $data['telephone'],
            'password'   => Hash::make($data['password']),
            'user_type'  => $data['user_type'],
            'is_active'  => true,
            'profile'    => $data['profile'] ?? null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // ✅ AJOUT: Créer le Custom Token Firebase aussi pour l'inscription
        $firebaseToken = $this->firebaseAuth->createCustomToken(
            $user->id,
            [
                'email' => $user->email,
                'user_type' => $user->user_type,
                'telephone' => $user->telephone,
            ]
        );

        return [
            'user' => $user,
            'token' => $token,
            'firebase_token' => $firebaseToken,  // ✅ AJOUT
            'redirect' => $this->getRedirectPath($user),
        ];
    }

    public function logout(User $user)
    {
        $user->tokens()->delete();
    }

    private function getRedirectPath(User $user): string
    {
        return match ($user->user_type) {
            'admin'        => '/admin/dashboard',
            'proprietaire' => '/proprietaire/dashboard',
            'locataire'    => '/locataire/dashboard',
            default        => '/home',
        };
    }
}

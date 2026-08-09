<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuthService $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    // ─────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────
    public function index()
    {
        return User::all();
    }

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────
    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['login'])
            ->orWhere('telephone', $credentials['login'])
            ->first();

        // Identifiants incorrects
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
            ]);
        }

        // Compte désactivé
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Votre compte est désactivé.'],
            ]);
        }

        // ✅ Compte non vérifié via OTP
        if (! $user->phone_verified_at) {
            throw ValidationException::withMessages([
                'login' => ['Veuillez vérifier votre compte via le code OTP reçu par email.'],
            ]);
        }

        // Supprimer anciens tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $firebaseToken = $this->firebaseAuth->createCustomToken(
            $user->id,
            [
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'telephone' => $user->telephone,
            ]
        );

        return [
            'user'           => $user,
            'token'          => $token,
            'firebase_token' => $firebaseToken,
            'redirect'       => $this->getRedirectPath($user),
        ];
    }

    // ─────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────
    public function register(array $data)
    {
        $user = User::create([
            'prenom'    => $data['prenom'],
            'nom'       => $data['nom'],
            'email'     => $data['email'],
            'telephone' => $data['telephone'],
            'password'  => Hash::make($data['password']),
            'user_type' => $data['user_type'],
            'is_active' => true,
            'profile'   => $data['profile'] ?? null,
        ]);

        // ✅ Pas de token ici
        // Le token Sanctum est donné UNIQUEMENT après vérification OTP
        // dans AuthController::verifyOtp()

        return [
            'user' => $user,
        ];
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────
    public function logout(User $user)
    {
        $user->tokens()->delete();
    }

    // ─────────────────────────────────────────────
    // REDIRECT PATH
    // ─────────────────────────────────────────────
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
<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
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

        return [
            'user' => $user,
            'token' => $token,
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

        return [
            'user' => $user,
            'token' => $token,
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

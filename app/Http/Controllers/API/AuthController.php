<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

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

        $data = $this->authService->login($request->only('login', 'password'));

        return response()->json([
            'message'  => 'Connexion réussie',
            'user'     => new UserResource($data['user']),
            'token'    => $data['token'],
            'redirect' => $data['redirect'],
        ]);

    }

    public function register(Request $request)
    {
        $request->validate([
            'prenom'     => 'required|string|max:255',
            'nom'        => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|unique:users,telephone',
            'password'   => 'required|string|min:6|confirmed',
            'user_type'  => 'required|in:proprietaire,locataire',
        ]);

        $data = $this->authService->register($request->all());

        return response()->json([
            'message'  => 'Inscription réussie',
            'user'     => new UserResource($data['user']),
            'token'    => $data['token'],
            'redirect' => $data['redirect'],
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }
}

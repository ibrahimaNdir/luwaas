<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Otp\OtpServiceInterface;   // ← interface OTP
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $authService;
    protected $otpService;

    public function __construct(
        AuthService $authService,
        OtpServiceInterface $otpService
    ) {
        $this->authService = $authService;
        $this->otpService  = $otpService;
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
            'prenom'    => 'required|string|max:255',
            'nom'       => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
            'password'  => 'required|string|min:6',
            'user_type' => 'required|in:proprietaire,locataire',
        ]);

        // 2. Validation spécifique pour propriétaire
        if ($request->user_type === 'proprietaire') {
            $request->validate([
                'cni' => 'required|string|unique:proprietaires,cni',
            ]);
        }

        try {
            DB::beginTransaction();

            // 3. Création du user via ton service
            $data = $this->authService->register([
                'prenom'    => $request->prenom,
                'nom'       => $request->nom,
                'email'     => $request->email,
                'telephone' => $request->telephone,
                'password'  => $request->password,
                'user_type' => $request->user_type,
            ]);

            /** @var \App\Models\User $user */
            $user = $data['user'];

            // 4. Création du profil spécifique
            if ($request->user_type === 'proprietaire') {
                $profil = Proprietaire::create([
                    'user_id'             => $user->id,
                    'cni'                 => $request->cni,
                    'proprietaire_id'     => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                    'trial_ends_at'       => now()->addDays(30), // ← ajouter
                    'subscription_status' => 'trial',            // ← ajouter
                ]);
            } else {
                $profil = Locataire::create([
                    'user_id'      => $user->id,
                    'cni'          => $request->cni,
                    'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                ]);
            }

            if (! $profil) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Erreur lors de la création du profil spécifique.',
                ], 500);
            }

            // 5. Génération + envoi OTP (par email)
            $otp = $this->otpService->generateOtp();

            $user->update([
                'phone_otp'            => $otp,
                'phone_otp_expires_at' => Carbon::now()->addMinutes(10),
            ]);

            // Ici on utilise l'email comme destinataire
            $this->otpService->sendOtp($user->email, $otp);

            DB::commit();

            // 6. On NE renvoie PAS de token ici
            return response()->json([
                'message' => 'Inscription réussie. Un code OTP a été envoyé sur votre email.',
                'user_id' => $user->id,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de l\'inscription : ' . $e->getMessage(),
            ], 500);
        }
    }
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp'     => 'required|string|size:6',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasVerifiedPhone()) {
            return response()->json(['message' => 'Téléphone déjà vérifié.'], 422);
        }

        if ($user->isOtpExpired()) {
            return response()->json(['message' => 'Code expiré. Demandez un nouveau code.'], 422);
        }

        if ($user->phone_otp !== $request->otp) {
            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        // ✅ OTP valide
        $user->update([
            'phone_verified_at'    => Carbon::now(),
            'phone_otp'            => null,
            'phone_otp_expires_at' => null,
        ]);

        // Maintenant on donne le token
        $token = $user->createToken('luwaas-token')->plainTextToken;

        return response()->json([
            'message' => 'Téléphone vérifié ! Bienvenue sur Luwaas.',
            'token'   => $token,
            'user'    => new UserResource($user),
        ]);
    }

    // 🆕 MÉTHODE : Renvoyer l'OTP
    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasVerifiedPhone()) {
            return response()->json(['message' => 'Téléphone déjà vérifié.'], 422);
        }

        $otp = $this->otpService->generateOtp();

        $user->update([
            'phone_otp'            => $otp,
            'phone_otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Toujours via email à ce stade
        $this->otpService->sendOtp($user->email, $otp);

        return response()->json(['message' => 'Nouveau code envoyé sur votre email !']);
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

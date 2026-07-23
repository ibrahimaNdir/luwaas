<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Otp\OtpServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────
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
            'message' => 'Connexion réussie',
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
            'firebase_token' => $data['firebase_token'],
            'redirect' => $data['redirect'],
        ]);
    }

    // ─────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'prenom'    => 'required|string|max:255',
            'nom'       => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
            'password'  => 'required|string|min:6',
            'user_type' => 'required|in:proprietaire,locataire',
        ]);

        try {
            DB::beginTransaction();

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

            if ($request->user_type === 'proprietaire') {
                $profil = Proprietaire::create([
                    'user_id'              => $user->id,
                    'proprietaire_id'      => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                    'subscription_status'  => 'free_trial',
                    'plan'                 => 'free',
                    'billing_cycle'        => null,
                    'trial_ends_at'        => Carbon::now()->addDays(15),
                    'subscription_ends_at' => null,
                    'cancelled_at'         => null,
                ]);
            } else {
                $profil = Locataire::create([
                    'user_id'      => $user->id,
                    'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                ]);
            }

            if (! $profil) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Erreur lors de la création du profil spécifique.',
                ], 500);
            }

            $otp = $this->otpService->generateOtp();

            $user->update([
                'phone_otp'             => Hash::make($otp),
                'phone_otp_expires_at'  => Carbon::now()->addMinutes(10),
                'otp_attempts'          => 0,
            ]);

            $this->otpService->sendOtp($user->email, $otp);

            DB::commit();

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

    // ─────────────────────────────────────────────
    // VERIFY OTP
    // ─────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp'     => 'required|string|size:6',
        ]);

        $user = User::findOrFail($request->user_id);

        // Déjà vérifié
        if ($user->phone_verified_at) {
            return response()->json(['message' => 'Compte déjà vérifié.'], 422);
        }

        // Trop de tentatives → forcer un nouveau code
        if ($user->otp_attempts >= 3) {
            return response()->json([
                'message' => 'Trop de tentatives. Demandez un nouveau code.'
            ], 429);
        }

        // Code expiré
        if ($user->isOtpExpired()) {
            return response()->json([
                'message' => 'Code expiré. Demandez un nouveau code.'
            ], 422);
        }

        // Code incorrect → incrémenter tentatives
        if (! Hash::check($request->otp, $user->phone_otp)) {
            $user->increment('otp_attempts');
            $remaining = 3 - $user->fresh()->otp_attempts;
            return response()->json([
                'message' => "Code incorrect. {$remaining} tentative(s) restante(s)."
            ], 422);
        }

        // ✅ OTP valide → activer le compte + nettoyer
        $user->update([
            'phone_verified_at'    => Carbon::now(),
            'phone_otp'            => null,
            'phone_otp_expires_at' => null,
            'otp_attempts'         => 0,
        ]);

        // Token donné seulement ici
        $token = $user->createToken('luwaas-token')->plainTextToken;

        return response()->json([
            'message' => 'Compte vérifié ! Bienvenue sur Luwaas.',
            'token'   => $token,
            'user'    => new UserResource($user),
        ]);
    }

    // ─────────────────────────────────────────────
    // RESEND OTP
    // ─────────────────────────────────────────────
    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // Déjà vérifié → inutile de renvoyer
        if ($user->phone_verified_at) {
            return response()->json(['message' => 'Compte déjà vérifié.'], 422);
        }

        $otp = $this->otpService->generateOtp();

        $user->update([
            'phone_otp'            => Hash::make($otp), // ✅ hashé
            'phone_otp_expires_at' => Carbon::now()->addMinutes(10),
            'otp_attempts'         => 0, // ✅ reset les tentatives
        ]);

        $this->otpService->sendOtp($user->email, $otp);

        return response()->json([
            'message' => 'Nouveau code envoyé sur votre email !'
        ]);
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    // ─────────────────────────────────────────────
    // INDEX (offres)
    // ─────────────────────────────────────────────
    public function index()
    {
        $offres = $this->authService->index();
        return response()->json($offres, 200);
    }
}

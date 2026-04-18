<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    // ─────────────────────────────────────────
    // 1. LISTE DES PLANS DISPONIBLES
    // ─────────────────────────────────────────

    public function plans()
    {
        $plans = Plan::where('is_active', true)
                     ->orderBy('price_xof')
                     ->get();

        return response()->json([
            'message' => 'Plans disponibles',
            'plans'   => $plans,
        ]);
    }

    // ─────────────────────────────────────────
    // 2. SOUSCRIRE À UN PLAN
    // ─────────────────────────────────────────

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $proprietaire = $request->user()->proprietaire;

        // Bloquer si déjà un abonnement actif
        if ($proprietaire->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Vous avez déjà un abonnement actif.',
                'code'    => 'ALREADY_SUBSCRIBED',
            ], 422);
        }

        try {
            $data = $this->subscriptionService->initiatePayment(
                $proprietaire,
                $request->plan_id
            );

            return response()->json([
                'message'         => 'Paiement initié. Redirigez l\'utilisateur vers l\'URL.',
                'payment_url'     => $data['payment_url'],
                'subscription_id' => $data['subscription_id'],
                'token'           => $data['token'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'initiation du paiement : ' . $e->getMessage(),
                'code'    => 'PAYMENT_INIT_FAILED',
            ], 500);
        }
    }

    // ─────────────────────────────────────────
    // 3. ANNULER L'ABONNEMENT
    // ─────────────────────────────────────────

    public function cancel(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        if (! $proprietaire->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Aucun abonnement actif à annuler.',
                'code'    => 'NO_ACTIVE_SUBSCRIPTION',
            ], 422);
        }

        $cancelled = $this->subscriptionService->cancelSubscription($proprietaire);

        if (! $cancelled) {
            return response()->json([
                'message' => 'Impossible d\'annuler l\'abonnement.',
                'code'    => 'CANCEL_FAILED',
            ], 500);
        }

        return response()->json([
            'message' => 'Abonnement annulé avec succès.',
        ]);
    }

    // ─────────────────────────────────────────
    // 4. STATUT DE L'ABONNEMENT EN COURS
    // ─────────────────────────────────────────

    public function status(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;

        return response()->json([
            'message'      => 'Statut de l\'abonnement',
            'subscription' => $proprietaire->subscriptionSummary(),
        ]);
    }
}
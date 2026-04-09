<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionActivatedMail;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // GET /api/plans
    // Liste tous les plans actifs
    // ─────────────────────────────────────────────────────────
    public function plans(): JsonResponse
    {
        $plans = Plan::active()->orderBy('price_xof')->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/subscription/status
    // Statut de l'abonnement du bailleur connecté
    // ─────────────────────────────────────────────────────────
    public function status(): JsonResponse
    {
        $proprietaire = auth()->user()->proprietaire;

        return response()->json([
            'subscription_status'  => $proprietaire->subscription_status,
            'plan'                 => $proprietaire->plan,
            'billing_cycle'        => $proprietaire->billing_cycle,
            'trial_ends_at'        => $proprietaire->trial_ends_at,
            'subscription_ends_at' => $proprietaire->subscription_ends_at,
            'trial_days_left'      => $proprietaire->trialDaysLeft(),
            'has_access'           => $proprietaire->hasAccess(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/subscription/initiate
    // Initier un paiement d'abonnement via PayDunya
    // ─────────────────────────────────────────────────────────
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan         = Plan::findOrFail($request->plan_id);
        $proprietaire = auth()->user()->proprietaire;

        // Créer la subscription en statut pending
        $subscription = Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'payment_gateway' => 'paydunya',
            'amount'          => $plan->price_xof,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);

        // Initier le paiement PayDunya
        $paydunyaResponse = $this->initiatePaydunya($subscription, $plan);

        if (!$paydunyaResponse['success']) {
            $subscription->delete();
            return response()->json([
                'message' => 'Erreur lors de l\'initiation du paiement.',
                'error'   => $paydunyaResponse['error'],
            ], 500);
        }

        // Stocker le token PayDunya
        $subscription->update([
            'paydunya_token' => $paydunyaResponse['token'],
        ]);

        return response()->json([
            'message'         => 'Paiement initié avec succès.',
            'payment_url'     => $paydunyaResponse['payment_url'],
            'subscription_id' => $subscription->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/subscription/webhook
    // Webhook PayDunya — confirmation du paiement
    // ─────────────────────────────────────────────────────────
    public function webhook(Request $request): JsonResponse
    {
        Log::info('PayDunya Webhook reçu', $request->all());

        $token = $request->input('data.bill.token');

        if (!$token) {
            return response()->json(['status' => 'token_missing'], 400);
        }

        $subscription = Subscription::where('paydunya_token', $token)->first();

        if (!$subscription) {
            Log::warning('Subscription non trouvée pour le token: ' . $token);
            return response()->json(['status' => 'not_found'], 404);
        }

        $billStatus = $request->input('data.bill.status');

        if ($billStatus === 'completed') {
            $this->activateSubscription($subscription, $request);
        } elseif ($billStatus === 'cancelled') {
            $subscription->update(['status' => 'cancelled']);
        }

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/subscription/cancel
    // Annuler l'abonnement courant
    // ─────────────────────────────────────────────────────────
    public function cancel(): JsonResponse
    {
        $proprietaire = auth()->user()->proprietaire;

        if (!$proprietaire->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Aucun abonnement actif à annuler.',
            ], 400);
        }

        $now = now();

        // Annuler la subscription active
        $proprietaire->activeSubscription()->update([
            'status'       => 'cancelled',
            'cancelled_at' => $now,
        ]);

        // Mettre à jour le proprietaire
        $proprietaire->update([
            'subscription_status' => 'cancelled',
            'cancelled_at'        => $now,
        ]);

        return response()->json([
            'message' => 'Abonnement annulé. Vous gardez l\'accès jusqu\'au ' . $proprietaire->subscription_ends_at->format('d/m/Y') . '.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Méthodes privées
    // ─────────────────────────────────────────────────────────

    private function initiatePaydunya(Subscription $subscription, Plan $plan): array
    {
        try {
            $mode    = config('services.paydunya.mode', 'test');
            $baseUrl = $mode === 'live'
                ? 'https://app.paydunya.com/api/v1'
                : 'https://app.paydunya.com/sandbox-api/v1';

            $response = Http::withHeaders([
                'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
                'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
                'PAYDUNYA-PUBLIC-KEY'  => config('services.paydunya.public_key'),
                'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
                'Content-Type'         => 'application/json',
            ])->post("{$baseUrl}/checkout-invoice/create", [
                'invoice' => [
                    'total_amount' => $subscription->amount,
                    'description'  => "Abonnement Luwaas - {$plan->name}",
                ],
                'store' => [
                    'name' => 'Luwaas',
                ],
                'actions' => [
                    'cancel_url'  => config('app.url') . '/payment/cancel',
                    'return_url'  => config('app.url') . '/payment/success',
                    'callback_url'=> config('app.url') . '/api/subscription/webhook',
                ],
                'custom_data' => [
                    'subscription_id' => $subscription->id,
                ],
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['token'])) {
                return [
                    'success'     => true,
                    'token'       => $data['token'],
                    'payment_url' => "https://app.paydunya.com/sandbox/checkout-invoice/confirm/{$data['token']}",
                ];
            }

            return ['success' => false, 'error' => $data['response_text'] ?? 'Erreur inconnue'];

        } catch (\Exception $e) {
            Log::error('PayDunya initiation error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function activateSubscription(Subscription $subscription, Request $request): void
    {
        $plan = $subscription->plan;
        $now  = now();

        $ends = $plan->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        // Activer la subscription
        $subscription->update([
            'status'          => 'active',
            'starts_at'       => $now,
            'ends_at'         => $ends,
            'transaction_ref' => $request->input('data.bill.transaction_id'),
            'payment_method'  => $request->input('data.bill.payment_method') ?? null,
        ]);

        // Mettre à jour le proprietaire
        $subscription->proprietaire->update([
            'subscription_status'  => 'active',
            'plan'                 => $plan->tier,
            'billing_cycle'        => $plan->billing_cycle,
            'subscription_ends_at' => $ends,
        ]);

        // Envoyer le mail de confirmation
        try {
            Mail::to($subscription->proprietaire->user->email)
                ->send(new SubscriptionActivatedMail($subscription));
        } catch (\Exception $e) {
            Log::error('Mail confirmation error: ' . $e->getMessage());
        }
    }
}
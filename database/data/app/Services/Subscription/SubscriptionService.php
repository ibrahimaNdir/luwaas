<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    // ─────────────────────────────────────────
    // 1. INITIER UN PAIEMENT
    // ─────────────────────────────────────────

    public function initiatePayment(
        Proprietaire $proprietaire,
        int $planId,
        string $operateur,
        ?string $telephone,
        string $ip
    ): array {
        $plan = Plan::findOrFail($planId);

        $subscription = Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'amount'          => $plan->price_xof,
            'payment_gateway' => 'paydunya',
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);

        Log::info("💳 Subscription créée", [
            'id'        => $subscription->id,
            'plan'      => $plan->name,
            'operateur' => $operateur,
        ]);

        $paydunyaData = $this->initierPaydunya($subscription, $plan, $telephone, $ip);

        $subscription->update(['paydunya_token' => $paydunyaData['token']]);

        return [
            'subscription_id' => $subscription->id,
            'payment_url'     => $paydunyaData['payment_url'],
            'token'           => $paydunyaData['token'],
        ];
    }

    // ─────────────────────────────────────────
    // 2. ANNULER UN ABONNEMENT
    // ─────────────────────────────────────────

    public function cancelSubscription(Proprietaire $proprietaire): bool
    {
        $subscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$subscription) return false;

        DB::transaction(function () use ($subscription, $proprietaire) {
            $subscription->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $proprietaire->update([
                'subscription_status' => 'cancelled',
                'cancelled_at'        => now(),
            ]);
        });

        return true;
    }

    // ─────────────────────────────────────────
    // 3. APPEL PAYDUNYA (privé)
    // ─────────────────────────────────────────

    private function initierPaydunya(
        Subscription $subscription,
        Plan $plan,
        ?string $telephone,
        string $ip
    ): array {
        $mode    = config('services.paydunya.mode', 'test');
        $baseUrl = $mode === 'live'
            ? 'https://app.paydunya.com/api/v1'
            : 'https://app.paydunya.com/sandbox-api/v1';

        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ])->post("{$baseUrl}/checkout-invoice/create", [
            'invoice' => [
                'total_amount' => (int) $subscription->amount,
                'description'  => "Luwaas – Abonnement {$plan->name} ({$plan->billing_cycle})",
            ],
            'store' => [
                'name'    => 'Luwaas',
                'tagline' => 'Gestion locative SaaS',
            ],
            'actions' => [
                'cancel_url'   => config('app.url') . '/abonnement/annule',
                'return_url'   => config('app.url') . '/abonnement/succes',
                'callback_url' => config('app.url') . '/api/webhook/paydunya',
            ],
            'custom_data' => [
                'subscription_id' => $subscription->id,
                'proprietaire_id' => $subscription->proprietaire_id,
                'reference'       => 'SUB-' . $subscription->id . '-' . strtoupper(substr(uniqid(), -6)),
            ],
        ]);

        if (!$response->successful() || ($response['response_code'] ?? null) !== '00') {
            Log::error("❌ Erreur PayDunya (abonnement)", $response->json());
            throw new \Exception("Erreur PayDunya : " . ($response['response_text'] ?? 'Inconnue'));
        }

        $token = $response->json('token');

        Log::info("✅ PayDunya OK (abonnement)", ['token' => $token]);

        return [
            'token'       => $token,
            'payment_url' => $response->json('response_text'),
        ];
    }
}
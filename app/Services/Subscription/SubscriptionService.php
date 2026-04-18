<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    // ─────────────────────────────────────────
    // 1. INITIER UN PAIEMENT D'ABONNEMENT
    // ─────────────────────────────────────────

    public function initiatePayment(Proprietaire $proprietaire, int $planId): array
    {
        $plan = Plan::findOrFail($planId);

        // Créer la subscription en "pending"
        $subscription = Subscription::create([
            'proprietaire_id'  => $proprietaire->id,
            'plan_id'          => $plan->id,
            'status'           => 'pending',
            'amount'           => $plan->price_xof,
            'payment_gateway'  => 'paydunya',
            'starts_at'        => now(),
            'ends_at'          => $plan->billing_cycle === 'monthly'
                                    ? now()->addMonth()
                                    : now()->addYear(),
        ]);

        // Initier la facture PayDunya
        $paydunyaData = $this->createPaydunyaInvoice($proprietaire, $subscription, $plan);

        // Stocker le token PayDunya pour retrouver la subscription dans le webhook
        $subscription->update([
            'paydunya_token' => $paydunyaData['token'],
        ]);

        return [
            'subscription_id' => $subscription->id,
            'payment_url'     => $paydunyaData['payment_url'],
            'token'           => $paydunyaData['token'],
        ];
    }

    // ─────────────────────────────────────────
    // 2. ACTIVER L'ABONNEMENT (après IPN PayDunya)
    // ─────────────────────────────────────────

    public function activateSubscription(string $paydunyaToken, string $transactionRef): bool
    {
        $subscription = Subscription::where('paydunya_token', $paydunyaToken)
                                    ->where('status', 'pending')
                                    ->first();

        if (! $subscription) {
            Log::warning('SubscriptionService: subscription non trouvée pour token ' . $paydunyaToken);
            return false;
        }

        DB::transaction(function () use ($subscription, $transactionRef) {
            // 1. Activer la subscription
            $subscription->update([
                'status'          => 'active',
                'transaction_ref' => $transactionRef,
            ]);

            // 2. Mettre à jour le propriétaire
            $subscription->proprietaire->update([
                'subscription_status'  => 'active',
                'subscription_ends_at' => $subscription->ends_at,
                'plan'                 => $subscription->plan->tier,
                'billing_cycle'        => $subscription->plan->billing_cycle,
            ]);
        });

        return true;
    }

    // ─────────────────────────────────────────
    // 3. ANNULER UN ABONNEMENT
    // ─────────────────────────────────────────

    public function cancelSubscription(Proprietaire $proprietaire): bool
    {
        $subscription = Subscription::where('proprietaire_id', $proprietaire->id)
                                    ->where('status', 'active')
                                    ->latest()
                                    ->first();

        if (! $subscription) {
            return false;
        }

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
    // 4. EXPIRER LES ABONNEMENTS (via commande)
    // ─────────────────────────────────────────

    public function expireOverdueSubscriptions(): int
    {
        // Expirer les trials terminés
        $expiredTrials = Proprietaire::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->update(['subscription_status' => 'expired']);

        // Expirer les abonnements payants terminés
        $expiredSubs = Proprietaire::where('subscription_status', 'active')
            ->where('subscription_ends_at', '<', now())
            ->update(['subscription_status' => 'expired']);

        // Marquer aussi les subscriptions comme expirées
        Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        return $expiredTrials + $expiredSubs;
    }

    // ─────────────────────────────────────────
    // 5. CRÉER LA FACTURE PAYDUNYA
    // ─────────────────────────────────────────

    private function createPaydunyaInvoice(
        Proprietaire $proprietaire,
        Subscription $subscription,
        Plan $plan
    ): array {
        // Configuration PayDunya
        \PayDunya\Setup::setMasterKey(config('paydunya.master_key'));
        \PayDunya\Setup::setPublicKey(config('paydunya.public_key'));
        \PayDunya\Setup::setPrivateKey(config('paydunya.private_key'));
        \PayDunya\Setup::setToken(config('paydunya.token'));

        if (config('app.env') !== 'production') {
            \PayDunya\Setup::setMode('test'); // mode sandbox
        }

        // Facture
        $invoice = new \PayDunya\Checkout\Invoice();
        $invoice->addItem(
            $plan->name . ' (' . $plan->billing_cycle . ')',
            1,
            $subscription->amount,
            $subscription->amount
        );
        $invoice->setTotalAmount($subscription->amount);
        $invoice->setDescription('Luwaas – Abonnement ' . $plan->name);

        // Store
        $store = new \PayDunya\Checkout\Store();
        $store->setName('Luwaas');
        $store->setCallbackURL(route('webhook.paydunya'));         // IPN
        $store->setReturnURL(route('subscription.success'));       // succès
        $store->setCancelURL(route('subscription.cancel'));        // annulation

        // Lancer la requête
        $checkoutInvoice = new \PayDunya\Checkout\CheckoutInvoice();
        $checkoutInvoice->create($invoice, $store);

        return [
            'token'       => $checkoutInvoice->getToken(),
            'payment_url' => $checkoutInvoice->getUrl(),
        ];
    }
}
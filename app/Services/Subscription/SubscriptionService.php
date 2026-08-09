<?php

namespace App\Services\Subscription;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class SubscriptionService
{
    // ... (ton constructeur et méthodes existantes) ...

    /**
     * Génère et stocke la facture PDF pour un abonnement
     */
    public function genererEtStockerFactureAbonnement(Subscription $subscription): string
    {
        // Chargement de la vue (on passe l'objet subscription pour les infos)
        $pdf = Pdf::loadView('pdf.facture_abonnement', ['subscription' => $subscription]);
        
        $path = 'factures/abonnements/facture_' . $subscription->id . '.pdf';
        
        Storage::disk('public')->put($path, $pdf->output());
        
        // On stocke le chemin dans le modèle si tu as une colonne pour cela
        // $subscription->update(['facture_path' => $path]);
        
        return $path;
    }
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    // ─────────────────────────────────────────
    // 1. INITIER UN PAIEMENT D'ABONNEMENT
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
            'payment_gateway' => config('luwaas.active_gateway', 'paydunya'),
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);

        Log::info('💳 Subscription créée', [
            'id'        => $subscription->id,
            'plan'      => $plan->name,
            'operateur' => $operateur,
        ]);

        // ── Créer la transaction locale
        $transaction = Transaction::create([
            'type'             => 'subscription_payment',
            'subscription_id'  => $subscription->id,
            'mode_paiement'    => $operateur,
            'montant'          => $subscription->amount,
            'statut'           => 'en_attente',
            'reference'        => 'SUB-' . $subscription->id . '-' . strtoupper(substr(uniqid(), -6)),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
            'expire_at'        => now()->addMinutes(30),
        ]);

        // ── Appel au gateway (plus aucun code PayDunya ici)
        $paymentData = $this->gateway->initiateCheckout($transaction, [
            'description'   => "Luwaas – Abonnement {$plan->name} ({$plan->billing_cycle})",
            'store_tagline' => 'Gestion locative SaaS',
            'cancel_url'    => config('app.url') . '/abonnement/annule',
            'return_url'    => config('app.url') . '/abonnement/succes',
            'callback_url'  => config('app.url') . '/api/webhook/payment',
            'custom_data'   => [
                'subscription_id' => $subscription->id,
                'proprietaire_id' => $subscription->proprietaire_id,
            ],
        ]);

        $transaction->update(['lien_paiement' => $paymentData['payment_url'] ?? null]);

        return [
            'subscription_id' => $subscription->id,
            'payment_url'     => $paymentData['payment_url'],
            'token'           => $paymentData['token'],
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
    // 3. CALCULER LE PRIX (dynamic pricing)
    // ─────────────────────────────────────────

    public function calculatePrice(Proprietaire $proprietaire, Plan $plan): float
    {
        if ($plan->price_xof !== null) {
            return (float) $plan->price_xof;
        }

        $nbBiens = $proprietaire->logements()->count();

        return ($plan->price_base_xof ?? 0) + ($nbBiens * ($plan->price_per_property_xof ?? 0));
    }
}
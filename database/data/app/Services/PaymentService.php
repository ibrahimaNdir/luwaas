<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    // ═══════════════════════════════════════════
    // PLANS
    // ═══════════════════════════════════════════

    public function getPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::where('is_active', true)
            ->orderBy('price_xof')
            ->get();
    }

    // ═══════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════

    public function validerInitiationLoyer(Paiement $paiement, int $locataireId): ?array
    {
        if ($paiement->locataire_id !== $locataireId) {
            return ['message' => 'Ce paiement ne vous appartient pas.', 'status' => 403];
        }

        if ($paiement->statut === 'payé') {
            return ['message' => 'Ce paiement est déjà réglé.', 'status' => 422];
        }

        $enCours = Transaction::where('type', 'rent_payment')
            ->where('paiement_id', $paiement->id)
            ->where('statut', 'en_attente')
            ->where('expire_at', '>', now())  // ← ajouté
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Une transaction est déjà en cours.',
                'transaction' => [
                    'id'        => $enCours->id,
                    'reference' => $enCours->reference,
                    'montant'   => $enCours->montant,
                    'lien_paiement' => $enCours->lien_paiement, // ← bonus : renvoie direct le lien existant
                ],
                'status' => 422,
            ];
        }

        return null;
    }

    public function validerInitiationAbonnement(Subscription $subscription): ?array
    {
        if ($subscription->status === 'active') {
            return ['message' => 'Cet abonnement est déjà actif.', 'status' => 422];
        }

        $enCours = Transaction::where('type', 'subscription_payment')
            ->where('subscription_id', $subscription->id)
            ->where('statut', 'en_attente')
            ->where('expire_at', '>', now())  // ← ajouté
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Un paiement abonnement est déjà en cours.',
                'transaction' => [
                    'id'            => $enCours->id,
                    'reference'     => $enCours->reference,
                    'montant'       => $enCours->montant,
                    'lien_paiement' => $enCours->lien_paiement, // ← bonus, même logique
                ],
                'status' => 422,
            ];
        }

        return null;
    }

    // ═══════════════════════════════════════════
    // LOYER
    // ═══════════════════════════════════════════


    public function initierLoyer(Paiement $paiement, string $operateur, ?string $telephone, string $ip): array
    {
        $transaction = Transaction::create([
            'type'             => 'rent_payment',
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => $operateur,
            'montant'          => $paiement->montant_attendu,
            'statut'           => 'en_attente',
            'reference'        => $this->genererReference('LOYER', $paiement->bail_id, $paiement->id),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
        ]);

        try {
            $paymentData = $this->appelerPaydunya($transaction, [
                'description'   => "Paiement {$paiement->type} - {$paiement->periode}",
                'store_tagline' => 'Bail ' . $paiement->bail_id,
                'cancel_url'    => config('app.url') . '/paiement/annule',
                'return_url'    => config('app.url') . '/paiement/succes',
                'callback_url'  => config('app.url') . '/api/webhook/paydunya',
            ]);
        } catch (\Exception $e) {
            $transaction->update(['statut' => 'rejete']);
            throw $e;
        }

        $transaction->refresh();

        return [$transaction, $paymentData];
    }

    // ═══════════════════════════════════════════
    // ABONNEMENT
    // ═══════════════════════════════════════════



    public function creerSubscription(Proprietaire $proprietaire, int $planId, string $operateur): Subscription
    {
        $plan = Plan::findOrFail($planId);

        return Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'amount'          => $plan->price_xof,
            'payment_gateway' => 'paydunya',
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    }

    public function initierAbonnement(Subscription $subscription, string $operateur, ?string $telephone, string $ip): array
    {
        $plan = $subscription->plan;

        $transaction = Transaction::create([
            'type'             => 'subscription_payment',
            'subscription_id'  => $subscription->id,
            'mode_paiement'    => $operateur,
            'montant'          => $subscription->amount,
            'statut'           => 'en_attente',
            'reference'        => $this->genererReference('SUB', $subscription->proprietaire_id, $subscription->id),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
        ]);

        Log::info("💳 Transaction abonnement créée", ['id' => $transaction->id]);

        try {
            $paymentData = $this->appelerPaydunya($transaction, [
                'description'   => "Luwaas – Abonnement {$plan->name} ({$plan->billing_cycle})",
                'store_tagline' => 'Gestion locative SaaS',
                'cancel_url'    => config('app.url') . '/abonnement/annule',
                'return_url'    => config('app.url') . '/abonnement/succes',
                'callback_url'  => config('app.url') . '/api/webhook/paydunya',
            ]);
        } catch (\Exception $e) {
            $transaction->update(['statut' => 'rejete']);
            throw $e;
        }

        $subscription->update(['paydunya_token' => $transaction->paydunyatoken]);

        $transaction->refresh();

        return [$transaction, $paymentData];
    }

    // ANNULER ABONNEMENT
    public function annulerAbonnement(Proprietaire $proprietaire): bool
    {
        $subscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$subscription) return false;

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $proprietaire->update([
            'subscription_status' => 'cancelled',
            'cancelled_at'        => now(),
        ]);

        Log::info("🚫 Abonnement annulé", [
            'subscription_id' => $subscription->id,
            'proprietaire_id' => $proprietaire->id,
        ]);

        return true;
    }

    // RENOUVELER ABONNEMENT
    public function renouvelerAbonnement(Proprietaire $proprietaire, int $planId, string $operateur, ?string $telephone, string $ip): array
    {
        // Marquer l'ancienne comme renouvelée
        Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', 'active')
            ->latest()
            ->first()?->update(['status' => 'renewed']);

        // Créer nouvelle subscription
        $subscription = $this->creerSubscription($proprietaire, $planId, $operateur);

        // Initier le paiement
        return $this->initierAbonnement($subscription, $operateur, $telephone, $ip);
    }



    // ═══════════════════════════════════════════
    // PRIVÉS
    // ═══════════════════════════════════════════

    private function genererReference(string $prefix, int $contextId, int $modelId): string
    {
        return strtoupper($prefix) . "-{$contextId}-{$modelId}-" . strtoupper(substr(uniqid(), -6));
    }

    private function appelerPaydunya(Transaction $transaction, array $options): array
    {
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
                'total_amount' => (int) $transaction->montant,
                'description'  => $options['description'],
            ],
            'store' => [
                'name'    => 'Luwaas',
                'tagline' => $options['store_tagline'],
            ],
            'actions' => [
                'cancel_url'   => $options['cancel_url'],
                'return_url'   => $options['return_url'],
                'callback_url' => $options['callback_url'],
            ],
            'custom_data' => [
                'transaction_id' => $transaction->id,
                'reference'      => $transaction->reference,
                'type'           => $transaction->type,
            ],
        ]);

        if (!$response->successful() || ($response['response_code'] ?? null) !== '00') {
            Log::error("❌ Erreur PayDunya", $response->json());
            throw new \Exception("Erreur PayDunya : " . ($response['response_text'] ?? 'Inconnue'));
        }

        $token = $response->json('token');
        $lienPaiement = $response->json('response_text'); // c'est bien l'URL ici

        $transaction->update([
            'paydunyatoken' => $token,
            'lien_paiement'     => $lienPaiement,
            'expire_at'         => now()->addMinutes(30),

        ]);

        Log::info("✅ PayDunya OK", ['transaction_id' => $transaction->id, 'type' => $transaction->type]);

        return [
            'payment_url' => $lienPaiement,
            'token'       => $token,
        ];
    }
}

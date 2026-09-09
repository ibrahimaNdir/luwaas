<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Payout;
use App\Models\Subscription;
<<<<<<< HEAD
=======
use App\Models\Transaction;
use App\Services\GatewayResolver;
use App\Gateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
use Illuminate\Support\Facades\Log;

class PaymentService
{
<<<<<<< HEAD
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected CommissionService $commissionService
    ) {}

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // PLANS
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
=======
    protected GatewayResolver $gatewayResolver;

    public function __construct(GatewayResolver $gatewayResolver)
    {
        $this->gatewayResolver = $gatewayResolver;
    }

    // ════════════════════════════════════════════════
    // PLANS
    // ═══════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function getPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::where('is_active', true)
            ->orderBy('price_xof')
            ->get();
    }

<<<<<<< HEAD
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // VALIDATION
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
=======
    // ════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

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
            ->where('expire_at', '>', now())
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Une transaction est déjà en cours.',
                'transaction' => [
<<<<<<< HEAD
                    'id'            => $enCours->id,
                    'reference'     => $enCours->reference,
                    'montant'       => $enCours->montant,
                    'lien_paiement' => $enCours->payment_url,
                    'gatewayToken'  => $enCours->gateway_token,
=======
                    'id'        => $enCours->id,
                    'reference' => $enCours->reference,
                    'montant'   => $enCours->montant,
                    'lien_paiement' => $enCours->lien_paiement,
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
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
            ->where('expire_at', '>', now())
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Un paiement abonnement est déjà en cours.',
                'transaction' => [
                    'id'            => $enCours->id,
                    'reference'     => $enCours->reference,
                    'montant'       => $enCours->montant,
<<<<<<< HEAD
                    'lien_paiement' => $enCours->payment_url,
                    'gatewayToken'  => $enCours->gateway_token,
=======
                    'lien_paiement' => $enCours->lien_paiement,
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
                ],
                'status' => 422,
            ];
        }

        return null;
    }

<<<<<<< HEAD
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // LOYER
    //� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
=======
    // ════════════════════════════════════════════════
    // LOYER
    // ═══════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function initierLoyer(Paiement $paiement, string $operateur, ?string $telephone, string $ip): array
    {
        $locataireId = $paiement->locataire_id;

        $erreur = $this->validerInitiationLoyer($paiement, $locataireId);
        if ($erreur) {
            return $erreur;
        }

        // Map operateur to channel (same value)
        $channel = $operateur;

        // Create transaction
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
            'expire_at'        => now()->addMinutes(30),
        ]);

<<<<<<< HEAD
        try {
            $paymentData = $this->gateway->initiateCheckout($transaction, [
                'description'   => "Paiement {$paiement->type} - {$paiement->periode}",
                'store_tagline' => 'Bail ' . $paiement->bail_id,
                'cancel_url'    => config('app.url') . '/paiement/annule',
                'return_url'    => config('app.url') . '/paiement/succes',
                'callback_url'  => config('app.url') . '/api/webhook/payment',
                'custom_data'   => ['paiement_id' => $paiement->id, 'type' => 'rent_payment'],
            ]);
        } catch (\Exception $e) {
=======
        // Get active gateway
        $gateway = $this->gatewayResolver->getActiveGateway();

        // Process pay-in via the active gateway
        $paymentResult = $gateway->processPayin(
            $paiement->montant_attendu,
            $telephone,
            $transaction->reference,
            $channel
        );

        if (!$paymentResult['success']) {
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
            $transaction->update(['statut' => 'rejete']);
            return [
                'message' => 'Erreur lors de l\'initiation du paiement : ' . $paymentResult['error'],
                'status'  => 500,
            ];
        }

<<<<<<< HEAD
        $transaction->update(['payment_url' => $paymentData['payment_url'] ?? null]);
=======
        // Update transaction with gateway info and token
        $transaction->update([
            'gateway_used'      => $gateway->getName(),
            'gateway_token'     => $paymentResult['token'] ?? null,
            'lien_paiement'     => $paymentResult['payment_url'] ?? null,
            'expire_at'         => now()->addMinutes(30),
        ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        $transaction->refresh();

        return [$transaction, $paymentResult];
    }

<<<<<<< HEAD
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // ABONNEMENT
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    public function creerSubscription(Proprietaire $proprietaire, int $planId, string $operateur): Subscription
    {
        $plan = Plan::findOrFail($planId);

        return Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'amount'          => $plan->price_xof,
            'payment_gateway' => config('luwaas.active_gateway', 'paydunya'),
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    }
=======
    // ════════════════════════════════════════════════
    // ABONNEMENT
    // ═══════════════════════════════════════════════
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function initierAbonnement(Subscription $subscription, string $operateur, ?string $telephone, string $ip): array
    {
        // Map operateur to channel (same value)
        $channel = $operateur;

        // Create transaction
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
            'expire_at'        => now()->addMinutes(30),
        ]);

<<<<<<< HEAD
        Log::info('���💳 Transaction abonnement créée', ['id' => $transaction->id']);

        try {
            $paymentData = $this->gateway->initiateCheckout($transaction, [
                'description'   => "Luwaas – Abonnement {$plan->name} ({$plan->billing_cycle})",
                'store_tagline' => 'Gestion locative SaaS',
                'cancel_url'    => config('app.url') . '/abonnement/annule',
                'return_url'    => config('app.url') . '/abonnement/succes',
                'callback_url'  => config('app.url') . '/api/webhook/payment',
                'custom_data'   => ['subscription_id' => $subscription->id, 'type' => 'subscription_payment'],
            ]);
        } catch (\Exception $e) {
=======
        // Get active gateway
        $gateway = $this->gatewayResolver->getActiveGateway();

        // Process pay-in via the active gateway
        $paymentResult = $gateway->processPayin(
            $subscription->amount,
            $telephone,
            $transaction->reference,
            $channel
        );

        if (!$paymentResult['success']) {
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
            $transaction->update(['statut' => 'rejete']);
            return [
                'message' => 'Erreur lors de l\'initiation du paiement : ' . $paymentResult['error'],
                'status'  => 500,
            ];
        }

<<<<<<< HEAD
        $transaction->update(['payment_url' => $paymentData['payment_url'] ?? null]);
=======
        // Update transaction with gateway info and token
        $transaction->update([
            'gateway_used'      => $gateway->getName(),
            'gateway_token'     => $paymentResult['token'] ?? null,
            'lien_paiement'     => $paymentResult['payment_url'] ?? null,
            'expire_at'         => now()->addMinutes(30),
        ]);

        // Update subscription with the gateway used (for compatibility)
        $subscription->update([
            'payment_gateway' => $gateway->getName(),
            'payment_method'  => $operateur,
            'gateway_token'   => $transaction->gateway_token,
        ]);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        $transaction->refresh();

        return [$transaction, $paymentResult];
    }

    // ════════════════════════════════════════════════
    // ANNULER ABONNEMENT
    // ═══════════════════════════════════════════════

    public function annulerAbonnement(Proprietaire $proprietaire): bool
    {
        $subscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$subscription) {
            return false;
        }

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $proprietaire->update([
            'subscription_status' => 'cancelled',
            'cancelled_at'        => now(),
        ]);

<<<<<<< HEAD
        Log::info('���🚫 Abonnement annulé', [
            'subscription_id' => $subscription->id,
            'proprietaire_id' => $proprietaire->id,
        ]);

=======
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        return true;
    }

    // ════════════════════════════════════════════════
    // RENOUVELER ABONNEMENT
    // ═══════════════════════════════════════════════

    public function renouvelerAbonnement(Proprietaire $proprietaire, int $planId, string $operateur, ?string $telephone, string $ip): array
    {
        // Marquer l'ancienne comme renouvelée
        $oldSubscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($oldSubscription) {
            $oldSubscription->update(['status' => 'renewed']);
        }

        // Créer nouvelle subscription et initier le paiement
        $subscription = $this->creerSubscription($proprietaire, $planId, $operateur);
<<<<<<< HEAD
        return $this->initierAbonnement($subscription, $operateur, $telephone, $ip);
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // REVERSEMENT BAILLEUR
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    /**
     * Reverse le montant NET au bailleur (loyer - commission Luwaas).
     *
     * @param  Proprietaire $proprietaire   Objet bailleur (avec payout_phone)
     * @param  float        $montantBrut    Montant total reçu du locataire
     * @param  Transaction  $initialTransaction  Transaction source (pour traçabilité)
     */
    public function redistributeToBailleur(Proprietaire $proprietaire, float $montantBrut, Transaction $initialTransaction): array
    {
        // ── Calcul de la commission Luwaas (taux depuis config/luwaas.php)
        $fraisLuwaas  = $this->commissionService->calculerFraisLuwaas($montantBrut);
        $montantNet   = $montantBrut - $fraisLuwaas;

        // ── Taux PSP pour le monitoring (lu depuis commission_rates DB)
        $pspRate   = $this->gateway->getPspRate($initialTransaction->mode_paiement);
        $fraisPsp  = $montantBrut * $pspRate;
        $marge     = $fraisLuwaas - $fraisPsp;

        Log::info('���📊 Répartition financière loyer', [
            'montant_brut'             => $montantBrut,
            'frais_luwaas'             => $fraisLuwaas,
            'montant_net_bailleur'     => $montantNet,
            'frais_psp_absorbes'       => $fraisPsp,
            'marge_luwaas'             => $marge,
        ]);

        // ── Enregistrer le payout avant l'appel
        $payout = Payout::create([
            'proprietaire_id' => $proprietaire->id,
            'transaction_id'  => $initialTransaction->id,
            'montant'         => $montantNet,
            'statut'          => 'pending',
        ]);

        try {
            $response = $this->gateway->directPayout(
                $proprietaire->payout_phone,
                $montantNet
            );

            $payout->update([
                'statut'             => 'success',
                'reference_paydunya' => $response['description'] ?? null,
            ]);

            return $response;
        } catch (\Exception $e) {
            $payout->update([
                'statut' => 'failed',
                'erreur' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // HELPERS PRIVÉS
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    private function genererReference(string $prefix, int $id1, int $id2): string
    {
        return strtoupper($prefix) . '-' . $id1 . '-' . $id2 . '-' . strtoupper(substr(uniqid(), -6));
=======

        // Initier le paiement
        return $this->initierAbonnement(
            $subscription,
            $operateur,
            $telephone ?? null,
            $ip
        );
    }

    // ════════════════════════════════════════════════
    // CRÉER ABONNEMENT
    // ═══════════════════════════════════════════════

    public function creerSubscription(Proprietaire $proprietaire, int $planId, string $operateur): Subscription
    {
        $plan = Plan::findOrFail($planId);

        // Determine active gateway at subscription creation time (for compatibility)
        $gateway = $this->gatewayResolver->getActiveGateway();

        return Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'amount'          => $plan->price_xof,
            'payment_gateway' => $gateway->getName(), // Set active gateway for compatibility
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    }

    // ════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════

    private function genererReference(string $prefix, int $contextId, int $modelId): string
    {
        return strtoupper($prefix) . "-{$contextId}-{$modelId}-" . strtoupper(substr(uniqid(), -6));
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
    }
}
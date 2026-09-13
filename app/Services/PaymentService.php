<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\GatewayResolver;
use App\Services\PhoneNumberFormatter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use App\Constants\PaymentStatus;

class PaymentService
{
    protected GatewayResolver $gatewayResolver;

    public function __construct(GatewayResolver $gatewayResolver)
    {
        $this->gatewayResolver = $gatewayResolver;
    }

    // ════════════════════════════════════════════════
    // PLANS
    // ════════════════════════════════════════════════

    public function getPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::where('is_active', true)->orderBy('price_xof')->get();
    }

    // ════════════════════════════════════════════════
    // VALIDATION
    // ════════════════════════════════════════════════

    public function validerInitiationLoyer(Paiement $paiement, int $locataireId): ?array
    {
        if ($paiement->locataire_id !== $locataireId) {
            return ['message' => 'Ce paiement ne vous appartient pas.', 'status' => 403];
        }

        if ($paiement->statut === PaymentStatus::PAYMENT_PAID) {
            return ['message' => 'Ce paiement est déjà réglé.', 'status' => 422];
        }

        $enCours = Transaction::where('type', 'rent_payment')
            ->where('paiement_id', $paiement->id)
            ->where('statut', PaymentStatus::PENDING)
            ->where('expire_at', '>', now())
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Une transaction est déjà en cours.',
                'transaction' => [
                    'id'            => $enCours->id,
                    'reference'     => $enCours->reference,
                    'montant'       => $enCours->montant,
                    'lien_paiement' => $enCours->lien_paiement,
                ],
                'status' => 422,
            ];
        }

        return null;
    }

    public function validerInitiationAbonnement(Subscription $subscription): ?array
    {
        if ($subscription->status === PaymentStatus::SUBSCRIPTION_ACTIVE) {
            return ['message' => 'Cet abonnement est déjà actif.', 'status' => 422];
        }

        $enCours = Transaction::where('type', 'subscription_payment')
            ->where('subscription_id', $subscription->id)
            ->where('statut', PaymentStatus::PENDING)
            ->where('expire_at', '>', now())
            ->first();

        if ($enCours) {
            return [
                'message'     => 'Un paiement abonnement est déjà en cours.',
                'transaction' => [
                    'id'            => $enCours->id,
                    'reference'     => $enCours->reference,
                    'montant'       => $enCours->montant,
                    'lien_paiement' => $enCours->lien_paiement,
                ],
                'status' => 422,
            ];
        }

        return null;
    }

    // ════════════════════════════════════════════════
    // LOYER
    // ════════════════════════════════════════════════

    public function initierLoyer(Paiement $paiement, string $operateur, ?string $telephone, string $ip): array
    {
        $erreur = $this->validerInitiationLoyer($paiement, $paiement->locataire_id);
        if ($erreur) {
            return $erreur;
        }

        $transaction = Transaction::create([
            'type'             => 'rent_payment',
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => $operateur,
            'montant'          => $paiement->montant_attendu,
            'statut'           => PaymentStatus::PENDING,
            'reference'        => $this->genererReference('LOYER', $paiement->bail_id, $paiement->id),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
            'expire_at'        => now()->addMinutes(
                App::environment('local', 'testing')
                    ? config('luwaas.sandbox.payment_timeout_minutes')
                    : config('luwaas.payment_timeout_minutes')
            ),
        ]);

        $gateway = $this->gatewayResolver->getActiveGateway();
        $formattedPhone = PhoneNumberFormatter::formatE164($telephone);

        $paymentResult = $gateway->processPayin(
            $paiement->montant_attendu,
            $formattedPhone,
            $transaction->reference,
            $operateur
        );

        if (!$paymentResult['success']) {
            $transaction->update(['statut' => PaymentStatus::FAILED]);
            return [
                'message' => 'Erreur lors de l\'initiation du paiement : ' . $paymentResult['error'],
                'status'  => 500,
            ];
        }

        $transaction->update([
            'gateway_used'  => $gateway->getName(),
            'gateway_token' => $paymentResult['token'] ?? null,
            'lien_paiement' => $paymentResult['payment_url'] ?? null,
            'expire_at'     => now()->addMinutes(
                App::environment('local', 'testing')
                    ? config('luwaas.sandbox.payment_timeout_minutes')
                    : config('luwaas.payment_timeout_minutes')
            ),
            'statut'        => PaymentStatus::VALID,
        ]);

        $transaction->refresh();

        return [$transaction, $paymentResult];
    }

    // ════════════════════════════════════════════════
    // ABONNEMENT
    // ════════════════════════════════════════════════

    public function initierAbonnement(Subscription $subscription, string $operateur, ?string $telephone, string $ip): array
    {
        $erreur = $this->validerInitiationAbonnement($subscription);
        if ($erreur) {
            return $erreur;
        }

        $transaction = Transaction::create([
            'type'             => 'subscription_payment',
            'subscription_id'  => $subscription->id,
            'mode_paiement'    => $operateur,
            'montant'          => $subscription->amount,
            'statut'           => PaymentStatus::PENDING,
            'reference'        => $this->genererReference('SUB', $subscription->proprietaire_id, $subscription->id),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
            'expire_at'        => now()->addMinutes(
                App::environment('local', 'testing')
                    ? config('luwaas.sandbox.payment_timeout_minutes')
                    : config('luwaas.payment_timeout_minutes')
            ),
        ]);

        $gateway = $this->gatewayResolver->getActiveGateway();
        $formattedPhone = PhoneNumberFormatter::formatE164($telephone);

        $paymentResult = $gateway->processPayin(
            $subscription->amount,
            $formattedPhone,
            $transaction->reference,
            $operateur
        );

        if (!$paymentResult['success']) {
            $transaction->update(['statut' => PaymentStatus::FAILED]);
            return [
                'message' => 'Erreur lors de l\'initiation du paiement : ' . $paymentResult['error'],
                'status'  => 500,
            ];
        }

        $transaction->update([
            'gateway_used'  => $gateway->getName(),
            'gateway_token' => $paymentResult['token'] ?? null,
            'lien_paiement' => $paymentResult['payment_url'] ?? null,
            'expire_at'     => now()->addMinutes(
                App::environment('local', 'testing')
                    ? config('luwaas.sandbox.payment_timeout_minutes')
                    : config('luwaas.payment_timeout_minutes')
            ),
            'statut'        => PaymentStatus::VALID,
        ]);

        $subscription->update([
            'payment_gateway' => $gateway->getName(),
            'payment_method'  => $operateur,
            'gateway_token'   => $transaction->gateway_token,
        ]);

        $transaction->refresh();

        return [$transaction, $paymentResult];
    }

    // ════════════════════════════════════════════════
    // ANNULER ABONNEMENT
    // ════════════════════════════════════════════════

    public function annulerAbonnement(Proprietaire $proprietaire): bool
    {
        $subscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', PaymentStatus::SUBSCRIPTION_ACTIVE)
            ->latest()
            ->first();

        if (!$subscription) {
            return false;
        }

        $subscription->update(['status' => PaymentStatus::SUBSCRIPTION_CANCELLED, 'cancelled_at' => now()]);

        $proprietaire->update([
            'subscription_status' => PaymentStatus::SUBSCRIPTION_CANCELLED,
            'cancelled_at'        => now(),
        ]);

        return true;
    }

    // ════════════════════════════════════════════════
    // RENOUVELER ABONNEMENT
    // ════════════════════════════════════════════════

    public function renouvelerAbonnement(Proprietaire $proprietaire, int $planId, string $operateur, ?string $telephone, string $ip): array
    {
        $oldSubscription = Subscription::where('proprietaire_id', $proprietaire->id)
            ->where('status', PaymentStatus::SUBSCRIPTION_ACTIVE)
            ->latest()
            ->first();

        if ($oldSubscription) {
            $oldSubscription->update(['status' => PaymentStatus::SUBSCRIPTION_RENEWED]);
        }

        $subscription = $this->creerSubscription($proprietaire, $planId, $operateur);

        return $this->initierAbonnement($subscription, $operateur, $telephone, $ip);
    }

    // ════════════════════════════════════════════════
    // CRÉER ABONNEMENT
    // ════════════════════════════════════════════════

    public function creerSubscription(Proprietaire $proprietaire, int $planId, string $operateur): Subscription
    {
        $plan    = Plan::findOrFail($planId);
        $gateway = $this->gatewayResolver->getActiveGateway();

        return Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => PaymentStatus::SUBSCRIPTION_PENDING,
            'amount'          => $plan->price_xof,
            'payment_gateway' => $gateway->getName(),
            'payment_method'  => $operateur,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    }

    // ════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ════════════════════════════════════════════════

    private function genererReference(string $prefix, int $contextId, int $modelId): string
    {
        return strtoupper($prefix) . "-{$contextId}-{$modelId}-" . strtoupper(substr(uniqid(), -6));
    }
}
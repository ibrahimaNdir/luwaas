<?php

namespace App\Traits;

use Carbon\Carbon;

trait Subscribable
{
    // ─────────────────────────────────────────
    // 1. VÉRIFICATIONS D'ACCÈS
    // ─────────────────────────────────────────

    public function isInTrial(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at instanceof Carbon
            && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active'
            && $this->subscription_ends_at instanceof Carbon
            && $this->subscription_ends_at->isFuture();
    }

    public function hasAccess(): bool
    {
        return $this->isInTrial() || $this->hasActiveSubscription();
    }

    // ─────────────────────────────────────────
    // 2. INFORMATIONS SUR LE TRIAL
    // ─────────────────────────────────────────

    public function trialDaysLeft(): int
    {
        if (! $this->isInTrial()) {
            return 0;
        }

        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    public function isTrialEndingSoon(int $days = 5): bool
    {
        return $this->isInTrial() && $this->trialDaysLeft() <= $days;
    }

    // ─────────────────────────────────────────
    // 3. INFORMATIONS SUR L'ABONNEMENT
    // ─────────────────────────────────────────

    public function currentPlan(): ?string
    {
        return $this->plan ?? null;
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_status === 'expired'
            || ($this->subscription_ends_at instanceof Carbon
                && $this->subscription_ends_at->isPast()
                && $this->subscription_status !== 'trial');
    }

    public function isCancelled(): bool
    {
        return $this->subscription_status === 'cancelled';
    }

    // ─────────────────────────────────────────
    // 4. RÉSUMÉ POUR LE FRONT / L'API
    // ─────────────────────────────────────────

    public function subscriptionSummary(): array
    {
        return [
            'status'          => $this->subscription_status,
            'plan'            => $this->currentPlan(),
            'has_access'      => $this->hasAccess(),
            'is_trial'        => $this->isInTrial(),
            'trial_days_left' => $this->trialDaysLeft(),
            'trial_ends_at'   => $this->trial_ends_at,
            'ends_at'         => $this->subscription_ends_at,
            'warning'         => $this->isTrialEndingSoon()
                                    ? "Il vous reste {$this->trialDaysLeft()} jour(s) d'essai."
                                    : null,
        ];
    }

    // ─────────────────────────────────────────
    // 5. FEATURE-GATING                        ← ✅ NOUVEAU
    // ─────────────────────────────────────────

    /**
     * Vérifie si le plan autorise une fonctionnalité
     * Features Pro : 'sms', 'export_excel', 'statistiques', 'cogestionnaires'
     */
    public function canUseFeature(string $feature): bool
    {
        // Trial → accès total (pour tester toutes les features)
        if ($this->isInTrial()) return true;

        // Enterprise → tout autorisé
        if ($this->plan === 'enterprise') return true;

        // Pro → tout autorisé
        if ($this->plan === 'pro') return true;

        // Starter → bloque les features Pro
        $proOnlyFeatures = ['sms', 'export_excel', 'statistiques', 'cogestionnaires'];

        if (in_array($feature, $proOnlyFeatures)) return false;

        return true;
    }

    /**
     * Vérifie si le bailleur peut ajouter un logement
     * selon la limite de son plan
     */
    public function canAddLogement(): bool
    {
        // Trial ou Enterprise → illimité
        if ($this->isInTrial() || $this->plan === 'enterprise') return true;

        $plan = \App\Models\Plan::where('tier', $this->plan ?? 'starter')
                                ->where('billing_cycle', $this->billing_cycle ?? 'monthly')
                                ->first();

        // Plan introuvable ou limite null = illimité
        if (! $plan || $plan->biens_max === null) return true;

        return $this->proprietes()->count() < $plan->biens_max;
    }

    /**
     * Vérifie si le bailleur peut ajouter un locataire
     * selon la limite de son plan
     */
    public function canAddLocataire(): bool
    {
        // Trial ou Enterprise ou Pro → illimité
        if ($this->isInTrial()) return true;
        if (in_array($this->plan, ['pro', 'enterprise'])) return true;

        $plan = \App\Models\Plan::where('tier', $this->plan ?? 'starter')
                                ->where('billing_cycle', $this->billing_cycle ?? 'monthly')
                                ->first();

        if (! $plan || $plan->locataires_max === null) return true;

        return $this->locataires()->count() < $plan->locataires_max;
    }

    /**
     * Retourne les limites actuelles du plan
     * → utile pour afficher les quotas côté front
     */
    public function planLimits(): array
    {
        // Trial → tout illimité
        if ($this->isInTrial()) {
            return [
                'biens_max'      => null,
                'locataires_max' => null,
                'biens_used'     => $this->proprietes()->count(),
                'locataires_used'=> $this->locataires()->count(),
            ];
        }

        $plan = \App\Models\Plan::where('tier', $this->plan ?? 'starter')
                                ->where('billing_cycle', $this->billing_cycle ?? 'monthly')
                                ->first();

        return [
            'biens_max'       => $plan?->biens_max,       // null = illimité
            'locataires_max'  => $plan?->locataires_max,  // null = illimité
            'biens_used'      => $this->proprietes()->count(),
            'locataires_used' => $this->locataires()->count(),
        ];
    }
}
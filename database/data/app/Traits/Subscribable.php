<?php

namespace App\Traits;

use App\Models\Plan;

use Carbon\Carbon;

/**
 * @property string $subscription_status
 * @property string|null $plan
 * @property string|null $billing_cycle
 * @property \Carbon\Carbon|null $subscription_ends_at
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon|null $cancelled_at
 */
trait Subscribable

{


    public function isFreeTrial(): bool
    {
        return $this->subscription_status === 'free_trial'
            && $this->plan === 'free';
    }

    public function isFreeTrialActive(): bool
    {
        return $this->isFreeTrial()
            && $this->trial_ends_at instanceof Carbon
            && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isFreeTrialActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at));
    }

    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, ['active', 'cancelled'], true)
            && $this->plan === 'pro'
            && $this->subscription_ends_at instanceof Carbon
            && $this->subscription_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->subscription_status === 'expired';
    }

    public function isPro(): bool
    {
        return $this->plan === 'pro' && $this->hasActiveSubscription();
    }

    // ─────────────────────────────────────────
    // 2. ACCÈS GLOBAL
    // ─────────────────────────────────────────

    public function hasAccess(): bool
    {
        return true;
    }

    public function canAccessBackOffice(): bool
    {
        return true;
    }

    // ─────────────────────────────────────────
    // 3. RÉSOLUTION DU PLAN
    // ─────────────────────────────────────────

    public function resolvedPlan(): ?Plan
    {
        if (($this->plan ?? 'free') === 'free') {
            return Plan::where('tier', 'free')
                ->whereNull('billing_cycle')
                ->where('is_active', true)
                ->first();
        }

        return Plan::where('tier', 'pro')
            ->where('billing_cycle', $this->billing_cycle ?? 'monthly')
            ->where('is_active', true)
            ->first();
    }

    // ─────────────────────────────────────────
    // 4. PUBLICATIONS
    // ─────────────────────────────────────────

    public function canPublishLogement(): bool
    {
        $plan = $this->resolvedPlan();

        if (! $plan) {
            return false;
        }

        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->count();

        // Cas 1 : Pro actif
        if ($this->hasActiveSubscription()) {
            if ($plan->publications_max === null) {
                return true;
            }

            return $publishedCount < $plan->publications_max;
        }

        // Cas 2 : gratuit 15 jours actif
        if ($this->isFreeTrialActive()) {
            if ($plan->publications_max === null) {
                return true;
            }

            return $publishedCount < $plan->publications_max;
        }

        // Cas 3 : gratuit expiré sans paiement
        return false;
    }

    public function publicationsLeft(): int
    {
        $plan = $this->resolvedPlan();

        if (! $plan) {
            return 0;
        }

        if (! $this->hasActiveSubscription() && ! $this->isFreeTrialActive()) {
            return 0;
        }

        if ($plan->publications_max === null) {
            return 999;
        }

        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->count();

        return max(0, $plan->publications_max - $publishedCount);
    }

    // ─────────────────────────────────────────
    // 5. LIMITES DU PLAN
    // ─────────────────────────────────────────

    public function planLimits(): array
    {
        $plan = $this->resolvedPlan();

        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->count();

        return [
            'plan' => $this->plan ?? 'free',
            'publications_max' => $plan?->publications_max,
            'publications_used' => $publishedCount,
            'publications_left' => $this->publicationsLeft(),
        ];
    }

    // ─────────────────────────────────────────
    // 6. RÉSUMÉ FRONT
    // ─────────────────────────────────────────

    public function subscriptionSummary(): array
    {
        return [
            'status' => $this->subscription_status,
            'plan' => $this->plan ?? 'free',
            'billing_cycle' => $this->billing_cycle,
            'has_access' => $this->hasAccess(),
            'can_access_backoffice' => $this->canAccessBackOffice(),
            'can_publish' => $this->canPublishLogement(),
            'is_pro' => $this->isPro(),
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->subscription_ends_at,
            'limits' => $this->planLimits(),
        ];
    }
    public function canUseFeature(string $feature): bool
    {
        $plan = $this->resolvedPlan();

        if (!$plan || !is_array($plan->features)) {
            return false;
        }

        // Si la feature est directement dans le plan
        if (in_array($feature, $plan->features)) {
            return true;
        }

        // Le plan pro-yearly a la clause "Tout le plan Pro" (pro-monthly)
        if (in_array('Tout le plan Pro', $plan->features)) {
            $proMonthlyPlan = Plan::where('tier', 'pro')->where('billing_cycle', 'monthly')->first();
            if ($proMonthlyPlan && is_array($proMonthlyPlan->features)) {
                if (in_array($feature, $proMonthlyPlan->features)) {
                    return true;
                }
                // Si la feature n'est pas dans pro-monthly, vérifier s'il hérite du plan Gratuit
                if (in_array('Tout le plan Gratuit', $proMonthlyPlan->features)) {
                    $freePlan = Plan::where('tier', 'free')->first();
                    if ($freePlan && is_array($freePlan->features) && in_array($feature, $freePlan->features)) {
                        return true;
                    }
                }
            }
        }

        // Le plan pro-monthly a une clause "Tout le plan Gratuit"
        if (in_array('Tout le plan Gratuit', $plan->features)) {
            $freePlan = Plan::where('tier', 'free')->first();
            if ($freePlan && is_array($freePlan->features) && in_array($feature, $freePlan->features)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Traits;

use App\Models\Plan;
use Carbon\Carbon;

/**
 * @property string $subscription_status
 * @property string|null $plan
 * @property string|null $billing_cycle
 * @property \Carbon\Carbon|null $subscription_ends_at
 * @property \Carbon\Carbon|null $cancelled_at
 */
trait Subscribable
{
    // ─────────────────────────────────────────
    // 1. STATUTS D'ABONNEMENT
    // ─────────────────────────────────────────

    public function isStarter(): bool
    {
        return $this->plan === 'starter';
    }

    public function isPro(): bool
    {
        return $this->plan === 'pro' && $this->hasActiveSubscription();
    }

    public function hasActiveSubscription(): bool
    {
        // Le plan Starter est gratuit et actif à vie (pas de date d'expiration)
        if ($this->isStarter()) {
            return true;
        }

        // Pour les plans payants (Pro), on vérifie le statut et la date de fin
        return $this->subscription_status === 'active'
            && $this->subscription_ends_at instanceof Carbon
            && $this->subscription_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->subscription_status === 'expired';
    }

    // ─────────────────────────────────────────
    // 2. ACCÈS GLOBAL & PUBLICATIONS
    // ─────────────────────────────────────────

    public function hasAccess(): bool
    {
        return true;
    }

    public function canAccessBackOffice(): bool
    {
        return true;
    }

    public function canPublishLogement(): bool
    {
        // Modèle v4 : La publication de logements est TOUJOURS GRATUITE ET ILLIMITÉE
        return true;
    }

    public function publicationsLeft(): int
    {
        // Illimité pour tous
        return 999;
    }

    // ─────────────────────────────────────────
    // 3. RÉSOLUTION DU PLAN
    // ─────────────────────────────────────────

    public function resolvedPlan(): ?Plan
    {
        $tier = $this->plan ?? 'starter';

        if ($tier === 'starter') {
            return Plan::where('tier', 'starter')
                ->where('is_active', true)
                ->first();
        }

        return Plan::where('tier', 'pro')
            ->where('billing_cycle', $this->billing_cycle ?? 'monthly')
            ->where('is_active', true)
            ->first();
    }

    // ─────────────────────────────────────────
    // 4. LIMITES DU PLAN
    // ─────────────────────────────────────────

    public function planLimits(): array
    {
        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->count();

        return [
            'plan' => $this->plan ?? 'starter',
            'publications_max' => null, // Illimité
            'publications_used' => $publishedCount,
            'publications_left' => 999,
        ];
    }

    // ─────────────────────────────────────────
    // 5. RÉSUMÉ FRONT
    // ─────────────────────────────────────────

    public function subscriptionSummary(): array
    {
        $plan = $this->resolvedPlan();
        
        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->count();

        $currentPrice = 0;
        if ($plan) {
            $currentPrice = $plan->price_base_xof + ($plan->price_per_property_xof * $publishedCount);
        }

        return [
            'status' => $this->subscription_status,
            'plan' => $this->plan ?? 'starter',
            'billing_cycle' => $this->billing_cycle,
            'has_access' => $this->hasAccess(),
            'can_access_backoffice' => $this->canAccessBackOffice(),
            'can_publish' => $this->canPublishLogement(), // Toujours true
            'is_pro' => $this->isPro(),
            'ends_at' => $this->subscription_ends_at, // Null pour Starter
            'current_price_estimation' => $currentPrice,
            'limits' => $this->planLimits(),
        ];
    }

    // ─────────────────────────────────────────
    // 6. VÉRIFICATION DES FEATURES
    // ─────────────────────────────────────────

    public function canUseFeature(string $feature): bool
    {
        $plan = $this->resolvedPlan();

        if (!$plan || !is_array($plan->features)) {
            return false;
        }

        if (in_array($feature, $plan->features)) {
            return true;
        }

        if (in_array('Tout le plan Pro', $plan->features)) {
            $proMonthlyPlan = Plan::where('tier', 'pro')->where('billing_cycle', 'monthly')->first();
            if ($proMonthlyPlan && is_array($proMonthlyPlan->features)) {
                if (in_array($feature, $proMonthlyPlan->features)) {
                    return true;
                }
                if (in_array('Tout le plan Starter', $proMonthlyPlan->features)) {
                    $starterPlan = Plan::where('tier', 'starter')->first();
                    if ($starterPlan && is_array($starterPlan->features) && in_array($feature, $starterPlan->features)) {
                        return true;
                    }
                }
            }
        }

        if (in_array('Tout le plan Starter', $plan->features)) {
            $starterPlan = Plan::where('tier', 'starter')->first();
            if ($starterPlan && is_array($starterPlan->features) && in_array($feature, $starterPlan->features)) {
                return true;
            }
        }

        return false;
    }
}

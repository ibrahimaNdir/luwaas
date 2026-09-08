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
 * @property \Carbon\Carbon|null $expiration_notified_at
 * @property \Carbon\Carbon|null $choice_notified_at
 * @property \Carbon\Carbon|null $choice_made_at
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
            && $this->plan !== null
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

        return Plan::where('tier', $this->tier)
            ->where('billing_cycle', $this->billing_cycle)
            ->where('is_active', true)
            ->first();
    }

    // ─────────────────────────────────────────
    // 4. PUBLICATIONS
    // ─────────────────────────────────────────

  

    // ─────────────────────────────────────
    // 5. EXPIRATION TRACKING
    // ─────────────────────────────────────
    public function getExpirationNotifiedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function setExpirationNotifiedAtAttribute($value): void
    {
        $this->attributes['expiration_notified_at'] = $value ? Carbon::parse($value) : null;
    }

    public function getChoiceNotifiedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function setChoiceNotifiedAtAttribute($value): void
    {
        $this->attributes['choice_notified_at'] = $value ? Carbon::parse($value) : null;
    }

    public function getChoiceMadeAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function setChoiceMadeAtAttribute($value): void
    {
        $this->attributes['choice_made_at'] = $value ? Carbon::parse($value) : null;
    }

    /**
     * Retourne true si nous sommes dans le délai de grâce de 3 jours
     * après le passage du statut à 'expired' ou 'cancelled' ET que la
     * première notification a déjà été envoyée.
     */
    public function isInGracePeriod(): bool
    {
        return $this->expiration_notified_at !== null
            && $this->subscription_status !== null
            && in_array($this->subscription_status, ['expired', 'cancelled'])
            && $this->expiration_notified_at->diffInHours(now()) < 72; // 3 jours
    }

    /**
     * Retourne true quand le délai de grâce est écoulé (≥ 3 j) et que
     * le propriétaire n’a pas encore fait de choix.
     */
    public function hasGracePeriodElapsed(): bool
    {
        return $this->expiration_notified_at !== null
            && $this->subscription_status !== null
            && in_array($this->subscription_status, ['expired', 'cancelled'])
            && $this->expiration_notified_at->diffInHours(now()) >= 72; // 3 jours
    }

    /**
     * Nombre de logements publiés **et vacants** (pas de bail actif).
     */
    public function vacantPublishedLogements()
    {
        return $this->logements()
            ->where('statut_publication', 'publie')
            ->whereDoesntHave('bails', fn ($q) => $q->where('status', 'actif'));
    }

    /**
     * Excédent de logements vacants publiés au‑delà de la limite Starter (5).
     */
    public function excessVacantPublishedCount(): int
    {
        $limit = 5; // limite Starter
        $excess = $this->vacantPublishedLogements()->count() - $limit;
        return $excess > 0 ? $excess : 0;
    }

    /**
     * Quand le propriétaire retrouve un abonnement Pro actif,
     * republie tous les logements qui avaient été archivés pour cause
     * d’expiration d’abonnement.
     */
    public function republishPreviouslyArchivedLogements(): void
    {
        $this->logements()
            ->where('statut_publication', 'archivé')
            ->where('archived_reason', 'subscription_expiration')
            ->update([
                'statut_publication' => 'publie',
                'archived_reason'    => null,
                'archived_at'        => null,
            ]);
    }

    // ─────────────────────────────────────
    // 6. LIMITES DU PLAN
    // ─────────────────────────────────────

    public function planLimits(): array
    {
        $plan = $this->resolvedPlan();

        if (! $plan) {
            return [
                'plan' => null,
                'publications_max' => null,
                'publications_used' => 0,
                'publications_left' => 0,
            ];
        }

        $publishedCount = $this->logements()
            ->where('statut_publication', 'publie')
            ->whereDoesntHave('bails', fn ($q) => $q->where('status', 'actif'))
            ->count();

        return [
            'plan' => $this->plan,
            'publications_max' => $plan->publications_max,
            'publications_used' => $publishedCount,
            'publications_left' => max(0, $plan->publications_max - $publishedCount),
        ];
    }

    // ─────────────────────────────────────
    // 7. RÉSUMÉ FRONT
    // ─────────────────────────────────────

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
            'plan' => $this->plan,
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
<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'tier',
        'billing_cycle',
        'price_xof',
        'publications_max',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features'            => 'array',
        'is_active'           => 'boolean',
        'price_xof'           => 'decimal:2',
        'publications_max'    => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMonthly($query)
    {
        return $query->where('billing_cycle', 'monthly');
    }

    public function scopeYearly($query)
    {
        return $query->where('billing_cycle', 'yearly');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isFree(): bool
    {
        return $this->price_xof == 0;
    }



     public function hasUnlimitedPublications(): bool
    {
        return is_null($this->publications_max);
    }
}
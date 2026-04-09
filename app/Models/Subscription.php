<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'proprietaire_id',
        'plan_id',
        'status',
        'payment_gateway',
        'payment_method',
        'paydunya_token',
        'transaction_ref',
        'amount',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at > now();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->ends_at < now();
    }
}
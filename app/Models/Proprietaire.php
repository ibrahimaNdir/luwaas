<?php

namespace App\Models;

use App\Traits\Subscribable;          // ✅ ajout
use Illuminate\Database\Eloquent\Model;

class Proprietaire extends Model
{
    use Subscribable;                  // ✅ ajout

    protected $fillable = [
        'user_id',
        'proprietaire_id',
        'is_actif',
        'cni',
        'trial_ends_at',              // ✅ ajout
        'subscription_status',        // ✅ ajout
        'subscription_ends_at',       // ✅ ajout
        'plan',                       // ✅ ajout
        'billing_cycle',              // ✅ ajout
        'cancelled_at',               // ✅ ajout
    ];

    protected $casts = [
        'is_actif'             => 'boolean',
        'cni'                  => 'encrypted',
        'trial_ends_at'        => 'datetime', // ✅ obligatoire pour isFuture()
        'subscription_ends_at' => 'datetime', // ✅ obligatoire pour isPast()
        'cancelled_at'         => 'datetime', // ✅ ajout
    ];

    // ─── Relations ───────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class); // ✅ ajout
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
                    ->where('status', 'active')
                    ->latest();                     // ✅ ajout
    }
}
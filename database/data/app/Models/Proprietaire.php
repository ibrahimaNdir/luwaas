<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\Subscribable;         
use Illuminate\Database\Eloquent\Model;

class Proprietaire extends Model
{
    use Subscribable   , HasFactory;
    
    /**
     * @property string|null $subscription_status
     * @property string|null $plan
     * @property string|null $billing_cycle
     * @property Carbon|null $subscription_ends_at
     * @property Carbon|null $trial_ends_at
     * @property Carbon|null $cancelled_at
     */

    protected $fillable = [
        'user_id',
        'proprietaire_id',
        'is_actif',
        'trial_ends_at',
        'subscription_status',        // 
        'subscription_ends_at',       // 
        'plan',                       // 
        'billing_cycle',              //
        'cancelled_at',               // 
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



    public function logements()
    {
        return $this->hasManyThrough(
            Logement::class,   // Model final
            Propriete::class,  // Model intermédiaire
            'proprietaire_id', // FK sur proprietes
            'propriete_id',    // FK sur logements
        );
    }


    public function locataires()
    {
        return $this->hasManyThrough(
            Locataire::class,
            Bail::class,
            'proprietaire_id',
            'id',
            'id',
            'locataire_id'
        );
    }
}

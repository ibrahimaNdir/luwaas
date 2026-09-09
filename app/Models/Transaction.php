<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'type',
        'paiement_id',
        'subscription_id',
        'reference',
        'gateway_token',
        'payment_url',
        'expire_at',
        'mode_paiement',
        'montant',
        'statut',
        'telephone_payeur',
        'ip_address',
        'metadata',
        'date_transaction',
    ];


    protected $casts = [
        'metadata'         => 'array',
        'date_transaction' => 'datetime',
        'expire_at'        => 'datetime',
    ];

    public function paiement()
    {
        return $this->belongsTo(Paiement::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
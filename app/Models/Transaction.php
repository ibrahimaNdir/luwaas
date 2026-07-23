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
        'paydunyatoken',
        'lien_paiement',   // ← à ajouter
        'expire_at',       // ← à ajouter
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
        'expire_at'        => 'datetime',  // ← à ajouter
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

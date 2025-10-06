<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'paiement_id',
        'montant',
        'statut',
        'provider',
        'transaction_ref',
        'frais',
        'raw_response',
        'date_transaction',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'date_transaction' => 'datetime',
    ];

    public function paiement()
    {
        return $this->belongsTo(Paiement::class);
    }
}

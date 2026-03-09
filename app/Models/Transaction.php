<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'reference_externe',
        'paiement_id',
        'mode_paiement',
        'montant',
        'statut',
        'reference',
        'telephone_payeur',
        'ip_address',
        'date_transaction',
        'metadata' 
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $fillable = [
        'locataire_id',
        'bail_id',
        'type',
        'montant_attendu',
        'montant_paye',
        'montant_restant',
        'statut',
        'date_echeance',
        'date_paiement',
        'periode',
    ];

    public function bail()
    {
        return $this->belongsTo(Bail::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

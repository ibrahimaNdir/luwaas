<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $fillable = [
        'locataire_id',
        'bail_id',
        'montant_attendu',
        'statut',
        'mois',
        'annee',
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

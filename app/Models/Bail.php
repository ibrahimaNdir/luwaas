<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bail extends Model
{
    protected $fillable = [
        'montant_loyer',
        'date_debut',
        'date_fin',
        'garantie',
        'statut',
        'logement_id',
        'locataire_id',
        'date_signature',
        'charges_mensuelles',
        'charges_incluses',
        'jour_echeance',
        'renouvellement_automatique',
    ];

    protected $casts = [
        'charges_incluses' => 'boolean',
        'renouvellement_automatique' => 'boolean',
        'clauses_additionnelles' => 'array',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_signature' => 'date',
    ];

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}

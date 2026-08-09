<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    use HasFactory;

    protected $table = 'demandes';

    protected $fillable = [
        'logement_id',
        'locataire_id',
        'proprietaire_id',
        'date_demande',
        'status',
        'date_acceptation',
        'rappel_envoye',
        'date_refus',
        'date_non_aboutie',
        'date_bail_cree',
        'motif_refus',
    ];

    protected $casts = [
        'date_demande'      => 'datetime',
        'date_acceptation'  => 'datetime',
        'date_refus'        => 'datetime',
        'date_non_aboutie'  => 'datetime',
        'date_bail_cree'    => 'datetime',
        'rappel_envoye'     => 'boolean',
    ];

    // ═══════════════════════════════════════════
    // RELATIONS
    // ═══════════════════════════════════════════

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function bail()
    {
        return $this->hasOne(Bail::class, 'demande_id');
    }
}
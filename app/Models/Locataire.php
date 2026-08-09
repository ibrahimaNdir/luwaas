<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Locataire extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $locataire): void {
            if (blank($locataire->locataire_id) && $locataire->user_id) {
                $locataire->locataire_id = 'LOC-' . str_pad((string) $locataire->user_id, 5, '0', STR_PAD_LEFT);
            }
        });
    }
    
    protected $fillable = [
        'user_id',
        'locataire_id',
        'is_actif',
        // ── Score de fiabilité
        'score_fiabilite',
        'total_paiements_en_ligne',
        'total_paiements_a_temps',
        'total_paiements_en_retard',
    ];

    protected $casts = [
        'is_actif'                   => 'boolean',
        'score_fiabilite'            => 'integer',
        'total_paiements_en_ligne'   => 'integer',
        'total_paiements_a_temps'    => 'integer',
        'total_paiements_en_retard'  => 'integer',
    ];

    /**
     * Libellé lisible du score pour le frontend.
     */
    public function scoreLabel(): string
    {
        return match(true) {
            $this->score_fiabilite >= 90 => 'Excellent',
            $this->score_fiabilite >= 75 => 'Bon',
            $this->score_fiabilite >= 50 => 'Moyen',
            default                      => 'Insuffisant',
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proprietaires()
    {
        return $this->hasManyThrough(Proprietaire::class, Bail::class);
    }

    public function baux()  
    {
        return $this->hasMany(Bail::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}

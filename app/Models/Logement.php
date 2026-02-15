<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logement extends Model
{
    protected $fillable = [
        'propriete_id',
        'numero',
        'superficie',
        'description',
        'nombre_pieces',
        'meuble',
        'etat',
        'typelogement',
        'prix_loyer',
        'statut_occupe',
        'statut_publication',
        'nombre_chambres',
        'nombre_salles_de_bain',

    ];

    protected $casts = [
        'is_occupe' => 'boolean',
        'meuble' => 'boolean',
    ];
    protected $attributes = [
        'statut_occupe' => 'disponible',       // par défaut le logement est disponible
        'statut_publication' => 'brouillon',   // par défaut en brouillon
    ];

    public function propriete()
    {
        return $this->belongsTo(Propriete::class);
    }

    public function baux()
    {
        return $this->hasMany(Bail::class);
    }

    public function photos()
    {
        return $this->hasMany(PhotoLogement::class);
    }

    // Dans le modèle Logement
    public function getTitreAfficheAttribute(): string
    {
        if (strtolower($this->typelogement) === 'studio') {
            return 'Studio';
        }
        return ucfirst($this->typelogement) . ' F' . ($this->nombre_chambres + 1);
    }

    public function getTotalEntreeAttribute(): float
    {
        return ($this->prix_loyer * $this->mois_caution) + ($this->prix_loyer * $this->mois_avance);
    }
}

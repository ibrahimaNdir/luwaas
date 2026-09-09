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
        'meuble',
        'etat',
        'typelogement',
        'prix_loyer',
        'statut_occupe',
        'statut_publication',
        'nombre_chambres',
        'nombre_salles_de_bain',
        'is_highlighted',
    ];

    protected $casts = [
        'statut_occupe'      => 'string',
        'meuble'             => 'boolean',
        'superficie'         => 'float',
        'prix_loyer'         => 'float',
        'is_highlighted'     => 'boolean',
    ];

    protected $attributes = [
        'statut_occupe'      => 'disponible',
        'statut_publication' => 'brouillon',
        'is_highlighted'     => false,
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

    /**
     * Retourne le nombre de pièces calculé à partir du type de logement et du nombre de chambres.
     *
     * @return int|null
     */
    public function getNombrePiecesAttribute()
    {
        switch ($this->typelogement) {
            case 'bureau':
            case 'local_commercial':
            case 'magasin':
                return null;
            case 'studio':
            case 'chambre':
                return 1;
            case 'maison':
            case 'villa':
            case 'appartement':
                return ($this->nombre_chambres ?? 0) + 1;
            default:
                return null;
        }
    }
}
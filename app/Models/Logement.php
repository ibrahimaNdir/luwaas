<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
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
        'type',
        'prix_loyer',
        'statut_occupe',
        'statut_publication',
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



}

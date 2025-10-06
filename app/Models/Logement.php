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
        'is_occupe',
        'nombre_pieces',
        'meuble',
        'etat',
    ];

    protected $casts = [
        'is_occupe' => 'boolean',
        'meuble' => 'boolean',
    ];

    public function propriete()
    {
        return $this->belongsTo(Propriete::class);
    }

    public function baux()
    {
        return $this->hasMany(Bail::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}

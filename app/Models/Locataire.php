<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Locataire extends Model
{
    protected $fillable = [
        'user_id',
        'locataire_id',
        'cni',
        'is_actif',


    ];

    protected $casts = [
        'is_actif' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
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

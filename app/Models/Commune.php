<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    protected $fillable = [
        'departement_id',
        'nom',
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }
}

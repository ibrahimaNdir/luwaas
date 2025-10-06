<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['nom'];

    public function departements()
    {
        return $this->hasMany(Departement::class);
    }

    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }
}

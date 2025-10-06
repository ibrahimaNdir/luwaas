<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    protected $fillable = [
        'region_id',
        'nom',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function communes()
    {
        return $this->hasMany(Commune::class);
    }

    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }
}

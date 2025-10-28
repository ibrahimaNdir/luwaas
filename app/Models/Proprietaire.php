<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proprietaire extends Model
{
    protected $fillable = [
        'user_id',
        'proprietaire_id',
        'is_actif',
        'cni'

    ];

    protected $casts = [
        'is_actif' => 'boolean',
    ];



    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}

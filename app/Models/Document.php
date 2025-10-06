<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'proprietaire_id',
        'type',
        'fichier_url',
        'statut',
        'commentaire_admin',
    ];

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }
}

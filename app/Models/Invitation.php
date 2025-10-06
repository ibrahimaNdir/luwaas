<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = [
        'invitation_id',
        'proprietaire_id',
        'logement_id',
        'locataire_id',
        'email_locataire',
        'statut',
        'token',
        'expire_at',
        'message',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
    ];

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }
}

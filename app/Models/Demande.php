<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{

   const STATUS_EN_ATTENTE = 'en_attente';
    const STATUS_ACCEPTEE = 'acceptee';
    const STATUS_REFUSEE = 'refusee';
    const STATUS_ANNULEE = 'annulee';
    const STATUS_CONVERTIE = 'convertie';

    protected $fillable = [
        'logement_id',
        'locataire_id',
        'proprietaire_id',
        'date_demande',
        'status', // N'oublie pas d'ajouter ça
    ];


    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

}

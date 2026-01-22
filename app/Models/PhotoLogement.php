<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoLogement extends Model
{
    use HasFactory;

    protected $table = 'photos_logements';

    protected $fillable = [
        'logement_id',
        'url',
        'principale',
        'ordre',
    ];

    // ✅ AJOUT : Accessor pour retourner l'URL complète
    public function getUrlCompleteAttribute()
    {
        if (str_starts_with($this->url, 'http')) {
            return $this->url;
        }

        // ✅ CHANGEMENT : Plus besoin de 'storage/'
        return url($this->url);
        // Résultat : http://192.168.1.2:8000/photos_logements/abc_123.jpg
    }


    // ✅ AJOUT : Pour inclure automatiquement url_complete dans le JSON
    protected $appends = ['url_complete'];

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }
}

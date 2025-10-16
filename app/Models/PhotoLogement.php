<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoLogement extends Model
{
    protected $table = 'photos_logements';

    protected $fillable = [
        'logement_id',
        'url',
        'principale',
        'ordre',
    ];

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }
    use HasFactory;

}

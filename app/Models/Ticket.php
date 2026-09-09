<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sujet',
        'message',
        'statut',
        'reponse_admin',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    // ── Relations

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes

    public function scopeOuvert($query)
    {
        return $query->where('statut', 'ouvert');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeFerme($query)
    {
        return $query->where('statut', 'ferme');
    }
}

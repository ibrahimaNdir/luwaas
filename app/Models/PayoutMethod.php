<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'proprietaire_id',
        'payout_channel',
        'payout_phone',
        'is_active',
        'is_default',
        'verified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the proprietair that owns the payout method.
     */
    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

    /**
     * Obtenir un libellé lisible pour le canal de versement
     */
    public function getChannelDisplayName(): string
    {
        return match ($this->payout_channel) {
            'bank_transfer' => 'Virement Bancaire',
            'orange_money' => 'Orange Money',
            'free_money' => 'Free Money',
            'wave' => 'Wave',
            default => ucfirst(str_replace('_', ' ', $this->payout_channel)),
        };
    }
}
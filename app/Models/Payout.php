<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'proprietaire_id',
        'gross_amount',
        'luwaas_commission',
        'payin_fee_absorbed',
        'payout_fee_absorbed',
        'net_amount_to_owner',
        'luwaas_net_benefit',
        'payout_method_id',
        'status',
        'processed_at',
        'period_start',
        'period_end',
        'reference',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'luwaas_commission' => 'decimal:2',
        'payin_fee_absorbed' => 'decimal:2',
        'payout_fee_absorbed' => 'decimal:2',
        'net_amount_to_owner' => 'decimal:2',
        'luwaas_net_benefit' => 'decimal:2',
        'processed_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // Relations
    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function payoutMethod()
    {
        return $this->belongsTo(PayoutMethod::class);
    }

    // Scopes utiles
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }
}

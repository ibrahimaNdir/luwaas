<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'gross_amount' => (float) $this->gross_amount,
            'luwaas_commission' => (float) $this->luwaas_commission,
            'payin_fee_absorbed' => (float) $this->payin_fee_absorbed,
            'payout_fee_absorbed' => (float) $this->payout_fee_absorbed,
            'net_amount_to_owner' => (float) $this->net_amount_to_owner,
            'luwaas_net_benefit' => (float) $this->luwaas_net_benefit,
            'status' => $this->status,
            'processed_at' => $this->processed_at ? $this->processed_at->toIso8601String() : null,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'reference' => $this->reference,
            'proprietaire_id' => $this->proprietaire_id,
            'payout_method' => [
                'id' => $this->payoutMethod->id,
                'channel' => $this->payoutMethod->payout_channel,
                'is_default' => $this->payoutMethod->is_default,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
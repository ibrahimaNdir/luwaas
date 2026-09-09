<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionStatutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this->resource is a Transaction model
        $data = [
            'transaction_id'   => $this->id,
            'type'             => $this->type,
            'statut'           => $this->statut,
            'reference'        => $this->reference,
            'date_transaction' => $this->date_transaction,
        ];

        if ($this->type === 'rent_payment' && $this->paiement) {
            $data['paiement_statut'] = $this->paiement->statut;
        }

        if ($this->type === 'subscription_payment' && $this->subscription) {
            $data['subscription_statut'] = $this->subscription->status;
        }

        return $data;
    }
}
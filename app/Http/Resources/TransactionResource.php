<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'               => $this->id,
            'type'             => $this->type,
            'reference'        => $this->reference,
            'mode_paiement'    => $this->mode_paiement,
            'montant'          => $this->montant,
            'statut'           => $this->statut,
            'date_transaction' => $this->date_transaction,
        ];

        if ($this->type === 'rent_payment' && $this->paiement) {
            $data['paiement'] = [
                'id'      => $this->paiement->id,
                'type'    => $this->paiement->type,
                'periode' => $this->paiement->periode,
                'statut'  => $this->paiement->statut,
            ];
        }

        return $data;
    }
}

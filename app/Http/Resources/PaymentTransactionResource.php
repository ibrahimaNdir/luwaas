<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
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

        // Include PayDunya specific token if present
        if (!is_null($this->paydunyaToken)) {
            $data['paydunyaToken'] = $this->paydunyaToken;
        }

        // Optionally include paiement details if needed (but payment initiation responses usually don't)
        // We'll keep it simple.

        return $data;
    }
}
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionDetailResource extends JsonResource
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
            'transaction' => [
                'id'                => $this->id,
                'type'              => $this->type,
                'reference'         => $this->reference,
                'gateway_token'     => $this->gateway_token,
                'mode_paiement'     => $this->mode_paiement,
                'montant'           => $this->montant,
                'statut'            => $this->statut,
                'telephone_payeur'  => $this->telephone_payeur,
                'date_transaction'  => $this->date_transaction,
                'created_at'        => $this->created_at,
            ],
        ];

        if ($this->type === 'rent_payment' && $this->paiement) {
            $data['paiement'] = [
                'id'              => $this->paiement->id,
                'type'            => $this->paiement->type,
                'periode'         => $this->paiement->periode,
                'montant_attendu' => $this->paiement->montant_attendu,
                'montant_paye'    => $this->paiement->montant_paye,
                'statut'          => $this->paiement->statut,
            ];

            if ($this->paiement->bail) {
                $data['bail'] = [
                    'id'      => $this->paiement->bail->id,
                    'logement' => $this->paiement->bail->logement->numero ?? null,
                ];
            }
        }

        if ($this->type === 'subscription_payment' && $this->subscription) {
            $data['subscription'] = [
                'id'        => $this->subscription->id,
                'status'    => $this->subscription->status,
                'plan'      => $this->subscription->plan->name ?? null,
                'starts_at' => $this->subscription->starts_at,
                'ends_at'   => $this->subscription->ends_at,
            ];
        }

        return $data;
    }
}
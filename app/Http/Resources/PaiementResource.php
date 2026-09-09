<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locataire_id' => $this->locataire_id,
            'bail_id' => $this->bail_id,
            'type' => $this->type,
            'montant_attendu' => (float) $this->montant_attendu,
            'montant_paye' => (float) $this->montant_paye,
            'montant_restant' => (float) $this->montant_restant,
            'statut' => $this->statut,
            'date_echeance' => $this->date_echeance ? $this->date_echeance->toISOString() : null,
            'date_paiement' => $this->date_paiement ? $this->date_paiement->toISOString() : null,
            'periode' => $this->periode,
            'verification_url' => $this->verification_url,
            // Relations conditionnelles (chargées uniquement si déjà eager loaded)
            'bail' => $this->whenLoaded('bail', fn() => [
                'id' => $this->bail->id,
                'reference' => $this->bail->reference,
                // éventuellement d'autres champs du bail si nécessaires
            ]),
            'locataire' => $this->whenLoaded('locataire', fn() => [
                'id' => $this->locataire->id,
                'prenom' => $this->locataire->prenom,
                'nom' => $this->locataire->nom,
                'email' => $this->locataire->user?->email,
                'telephone' => $this->locataire->user?->telephone,
            ]),
        ];
    }
}

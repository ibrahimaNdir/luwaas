<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementBailleursRessource extends JsonResource
{

    public function toArray($request){

        return [
            'id'            => $this->id,
            'montant'       => $this->montant_attendu,   // ou montant_paye si tu as ce champ
            'statut'        => $this->statut,            // 'payé', 'en_retard', etc.
            'periode'       => $this->periode,           // "Janvier 2025"
            'date_echeance' => $this->date_echeance,
            'date_paiement' => $this->date_paiement,

            // Infos locataire
            'locataire' => [
                'id'        => $this->locataire?->id,
                'nom'       => $this->locataire?->user?->nom ?? $this->locataire?->nom ?? null,
                'prenom'    => $this->locataire?->user?->prenom ?? $this->locataire?->prenom ?? null,
                'telephone' => $this->locataire?->telephone ?? null,
            ],

            // Infos logement
            'logement' => [
                'id'      => $this->bail?->logement?->id,
                'titre'   => $this->bail?->logement?->titre
                    ?? $this->bail?->logement?->numero
                        ?? null,
                'adresse' => $this->bail?->logement?->adresse ?? null,
            ],

            // Infos propriété (optionnel)
            'propriete' => [
                'id'  => $this->bail?->logement?->propriete?->id,
                'nom' => $this->bail?->logement?->propriete?->nom ?? null,
            ],

        ];
    }



}

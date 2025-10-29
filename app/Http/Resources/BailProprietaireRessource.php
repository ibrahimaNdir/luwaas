<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BailProprietaireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement' => [
                // 'id'         => $this->logement->id,
                'titre'      => $this->logement->titre,
                'adresse'    => $this->logement->adresse,
                'type'       => $this->logement->type,
                // Ajoute d'autres champs utiles selon ton modèle logement
            ],
            'locataire' => [
                // 'id'         => $this->locataire->id,
                'nom'        => $this->locataire->nom,
                'telephone'  => $this->locataire->telephone,
                //'email'      => $this->locataire->email,
                // Ajoute autre info pertinente
            ],
            'charges_mensuelles'   => $this->charges_mensuelles,
            'caution'              => $this->caution,
            'montant_loyer'        => $this->montant_loyer,
            'cautions_a_payer'     => $this->cautions_a_payer,
            'date_debut'           => $this->date_debut,
            'date_fin'             => $this->date_fin,
            'jour_echeance'        => $this->jour_echeance,
            'renouvellement_automatique' => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,  // <-- toujours à jour côté API !
            'statut_db'            => $this->statut,            // optionnel : statut “brut” en DB
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at
        ];
    }

}

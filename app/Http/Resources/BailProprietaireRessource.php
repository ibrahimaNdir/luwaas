<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
                'titre'      => $this->logement->propriete->titre,
                'adresse'    => $this->logement->propriete->adresse,
                'type'       => $this->logement->type,
                'numero'     => $this->logement->numero,
                // Ajoute d'autres champs utiles selon ton modèle logement
            ],
            'locataire' => [
                // 'id'         => $this->locataire->id,
                'nom'        => $this->locataire->user->nom,
                'telephone'  => $this->locataire->user->telephone,
                'prenom'  => $this->locataire->user->prenom
                //'email'      => $this->locataire->email,
                // Ajoute autre info pertinente
            ],
            'charges_mensuelles'   => $this->charges_mensuelles,
            'caution'              => $this->caution,
            'montant_loyer'        => $this->montant_loyer,
            'cautions_a_payer'     => $this->cautions_a_payer,
            'date_debut'           => Carbon::parse($this->date_debut)->format('Y-m-d'),
            'date_fin'             => Carbon::parse($this->date_fin)->format('Y-m-d'),
            'jour_echeance'        => $this->jour_echeance,
            'renouvellement'       => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,  // <-- toujours à jour côté API !
            'statut_db'            => $this->statut,            // optionnel : statut “brut” en DB
            //'created_at'           => $this->created_at,
            //'updated_at'           => $this->updated_at
        ];
    }

}

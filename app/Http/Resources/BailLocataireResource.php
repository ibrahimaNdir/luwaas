<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BailLocataireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement' => [
              //  'id'         => $this->logement->id,
                'titre'      => $this->logement->titre,
                'adresse'    => $this->logement->adresse,
                'type'       => $this->logement->type,
                // Ajoute d'autres champs qui intéressent le locataire
            ],
            'bailleur' => [
                // Le propriétaire est accessible via la propriété du logement
               // 'id'         => $this->logement->propriete->proprietaire->id ?? null,
                'nom'        => $this->logement->propriete->proprietaire->nom ?? null,
                'telephone'  => $this->logement->propriete->proprietaire->telephone ?? null,
                'prenom '      => $this->logement->propriete->proprietaire->prenom ?? null,
            ],
            'charges_mensuelles'   => $this->charges_mensuelles,
            'caution'              => $this->caution,
            'montant_loyer'        => $this->montant_loyer,
            'cautions_a_payer'     => $this->cautions_a_payer,
            'date_debut'           => $this->date_debut,
            'date_fin'             => $this->date_fin,
            'jour_echeance'        => $this->jour_echeance,
            'renouvellement_automatique' => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,  // dynamique et toujours à jour !
            'statut_db'            => $this->statut,            // optionnel
            //'created_at'           => $this->created_at,
            //'updated_at'           => $this->updated_at
        ];
    }
}

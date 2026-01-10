<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
                //'ids'=> $this->locataire->id,
               //'id'         => $this->id,
                'titre'      => $this->logement->propriete->titre,
                'adresse'    => $this->logement->propriete->adresse,
                'type'       => $this->logement->propriete->type,
                'numero'     => $this->logement->numero,
                // Ajoute d'autres champs qui intéressent le locataire
            ],
            'bailleur' => [
                'prenom'    => $this->logement->propriete->proprietaire->user->prenom ,
                'nom'       => $this->logement->propriete->proprietaire->user->nom ,
                'telephone' => $this->logement->propriete->proprietaire->user->telephone,
            ],
            'charges_mensuelles'   => $this->charges_mensuelles,
            'caution'              => $this->caution,
            'montant_loyer'        => $this->montant_loyer,
            'cautions_a_payer'     => $this->cautions_a_payer,
            'date_debut'           => Carbon::parse($this->date_debut)->format('Y-m-d'),
            'date_fin'             => Carbon::parse($this->date_fin)->format('Y-m-d'),
            'jour_echeance'        => $this->jour_echeance,
            'renouvellement_automatique' => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,  // dynamique et toujours à jour !
            //'statut_db'            => $this->statut,            // optionnel



            //'created_at'           => $this->created_at,
            //'updated_at'           => $this->updated_at
        ];
    }
}

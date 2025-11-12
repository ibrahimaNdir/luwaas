<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BailAdminRessource extends JsonResource
{
    public function toArray($request){
        return [
           // 'id' => $this->id,
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
            'bailleur' => [
                // 'id'         => $this->locataire->id,
                'nom'        => $this->logement->propriete->proprietaire->user->prenom ,
                'telephone'  => $this->logement->propriete->proprietaire->user->telephone ,
                'prenom'     => $this->logement->propriete->proprietaire->user->prenom ,
                //'email'      => $this->locataire->email,
                // Ajoute autre info pertinente
            ],
            //'charges_mensuelles'   => $this->charges_mensuelles,
            //'caution'              => $this->caution,
            'montant_loyer'        => $this->montant_loyer,
            //'cautions_a_payer'     => $this->cautions_a_payer,
            'date_debut'           => Carbon::parse($this->date_debut)->format('Y-m-d'),
            'date_fin'             => Carbon::parse($this->date_fin)->format('Y-m-d'),
            //'jour_echeance'        => $this->jour_echeance,
            'renouvellement'       => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,  // <-- toujours à jour côté API !
            //'statut_db'            => $this->statut,



        ];
    }

}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementDetailsRessources extends JsonResource
{
    public function toArray($request){
        return [
            'logement' => [
                //  'id'         => $this->id,
                'periode'      => $this->periode,
                'adresse'    => $this->statut,
                'date echeance '      =>$this->date_echeance ,
                'montant'   =>$this->montant_attendu,
                'Logement'=> $this->bail->logement->numero ,
                'type'=> $this->bail->logement->type,
                 'nom'        => $this->bail->logement->propriete->proprietaire->user->nom,
                          'telephone'  => $this->bail->logement->propriete->proprietaire->user->prenom,

            ],


        ];
    }

}

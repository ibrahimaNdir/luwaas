<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementLocataireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            'logement' => [
                //  'id'         => $this->id,
                'titre'      => $this->periode,
                'adresse'    => $this->statut,
                'date echeance '      =>$this->date_echeance ,
                'montant'   =>$this->montant_attendu

            ],
            'bailleur'=>[
                'nom'        => $this->bail->logement->propriete->proprietaire->user->nom,
                'telephone'  => $this->bail->logement->propriete->proprietaire->user->telephone,
                'prenom'  => $this->bail->logement->propriete->proprietaire->user->prenom,
            ]



        ];



    }

}

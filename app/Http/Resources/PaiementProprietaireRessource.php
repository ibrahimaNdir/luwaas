<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementProprietaireRessource extends JsonResource
{
    public function toArray($request) {
        return [

            // 'id' => $this->id,
            'logement' => [
               // 'id'         => $this->id,
                'titre'      => $this->periode,
                'adresse'    => $this->statut,
                'date '      =>$this->date_echeance ,
                'montant'   =>$this->montant_attendu


                // Ajoute d'autres champs utiles selon ton modèle logement
            ],



        ];
    }


}

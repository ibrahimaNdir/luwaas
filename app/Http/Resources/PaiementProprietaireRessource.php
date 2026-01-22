<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementProprietaireRessource extends JsonResource
{
    public function toArray($request) {
        return [

            // 'id' => $this->id,
            'logement' => [
               //  'id'         => $this->id,
                'periode'      => $this->periode,
                'locataire'   => $this->bail->locataire->user->name,
                'adresse'    => $this->statut,
                'date_echeance'      =>$this->date_echeance ,
                'montant'   =>$this->montant_attendu,
                'date_paiement' =>$this->date_paiement,
                'statut' =>$this->statut,
                // Ajoute d'autres champs utiles selon ton modèle logement
            ],



        ];
    }


}

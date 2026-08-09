<?php

namespace App\Http\Resources;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BauxLocataireRessource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
           // 'id' => $this->id,
            'logement' => [
                'id'         => $this->id,
                //'titre'      => $this->logement->propriete->titre,
                'adresse'    => $this->logement->propriete->adresse,
                'type'       => $this->logement->type,
                'numero'     => $this->logement->numero,
                'surface'    => $this->logement->superficie,

                // Ajoute d'autres champs utiles selon ton modèle logement
            ],

            'statut'               => $this->statut_dynamique,  // <-- toujours à jour côté API !
           

        ];
    }



}

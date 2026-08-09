<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementLocataireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            
            [ 
                'id' => $this->id,
                'periode'      => $this->periode,
                'statut'    => $this->statut,
                'date echeance '      =>$this->date_echeance ,
                'montant'   =>$this->montant_attendu ,

            ],




        ];



    }

}

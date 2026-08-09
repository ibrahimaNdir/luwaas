<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProprieteDetailResource extends JsonResource
{
    public function toArray($request)
    {
        // ✅ Accéder aux bonnes propriétés
        $propriete = $this->resource->propriete;
        $stats = $this->resource->stats;

        return [
            'id' => $propriete->id,
            'titre' => $propriete->titre,
            'adresse' => $propriete->adresse,
            'description' => $propriete->description,
            'type' => $propriete->type,
                'localisation' => [
                    'region' => $propriete->region->nom,
                    'departement' => $propriete->departement->nom,
                    'commune' => $propriete->commune->nom,
                ],
            'stats' => $stats,
        ];
    }
}
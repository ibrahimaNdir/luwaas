<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProprieteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'titre'       => ucfirst($this->titre),
            'type'        => $this->type,
            'adresse'     => $this->adresse, // orthographe corrigée
            'description' => $this->description,

          /*  'geo' => [
                'latitude'  => $this->latitude,
                'longitude' => $this->longitude,
            ],*/

            'localisation' => [
                'region'      => $this->region->nom,
                'departement' => $this->departement->nom,
                'commune'     => $this->commune->nom,
            ],

            'proprietaire' => $this->whenLoaded('proprietaire', fn () => [
                'id'        => (int) $this->proprietaire->id,
                'telephone' => $this->proprietaire->telephone,
            ]),

            // Exemple si vous voulez exposer les logements plus tard :
            // 'logements' => LogementResource::collection($this->whenLoaded('logements')),
        ];

    }
}

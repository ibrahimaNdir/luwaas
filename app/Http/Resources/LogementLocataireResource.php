<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementLocataireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => ucfirst($this->type),
            'superficie' => $this->superficie . ' m²',

            // Formatage avec F devant le nombre
            'nombre_pieces_format' => $this->typelogement === 'studio'
                ? 'Studio'
                : 'F' . $this->nombre_pieces,

            'nombre_pieces' => $this->nombre_pieces,
            'est_meuble' => (bool) $this->meuble,
            'etat' => ucfirst($this->etat),
            'description' => $this->description,

            // Prix du loyer
            'loyer_mensuel' => number_format($this->prix_loyer) . ' FCFA',
            
            // Informations de la propriété liées
            'propriete' => [
                'adresse' => $this->propriete->adresse,
                'commune' => $this->propriete->commune->nom,
            ],

            // Photos du logement - formatées proprement
            'photos' => $this->photos->map(function($photo) {
                return [
                    'id' => $photo->id,
                    'url' => url($photo->chemin),  // URL complète
                    'legende' => $photo->legende ?? null,
                    'est_principale' => (bool) $photo->est_principale,
                ];
            }),

        ];

    }
}

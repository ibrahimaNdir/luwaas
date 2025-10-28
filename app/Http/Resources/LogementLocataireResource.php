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
            'type' => ucfirst($this->typelogement),
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
            'loyer_mensuel' => number_format($this->prix_indicatif, 0, ',', ' ') . ' FCFA',
            'loyer_montant' => (float) $this->prix_indicatif,

            // Informations de la propriété liées
            'propriete' => [
                'adresse' => $this->propriete->adresse,
                'ville' => $this->propriete->ville,
                'commune' => $this->propriete->commune,
            ],

            // Contact du bailleur/propriétaire
            'proprietaire' => [
                'telephone' => $this->propriete->proprietaire->telephone,
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

            // Disponibilité
            'disponible' => $this->statut_occupe === 'disponible',
        ];

    }
}

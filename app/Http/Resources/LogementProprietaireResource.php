<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementProprietaireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            //'numero' => $this->numero,

            'type' => ucfirst($this->typelogement),
            'nombre_pieces' => 'F'. $this->nombre_pieces,
            'superficie' => $this->superficie . ' m²',
            'est_meuble' => (bool) $this->meuble,
            'etat' => ucfirst($this->etat),
            'description' => $this->description,

            // Informations financières complètes
            'prix' => number_format($this->prix_loyer, 0, ',', ' ') . ' FCFA',

            // Statuts (important pour la gestion)
            'statut_occupe' => $this->statut_occupe,
           // 'disponible' => $this->statut_occupe === 'disponible',
            'statut_publication' => $this->statut_publication,
            //'publie' => $this->statut_publication === 'publie',

            'propriete' => [
                'adresse' => $this->propriete->adresse,
                'commune' => $this->propriete->commune->nom,
               // 'communeid' => $this->propriete->commune->id,
                'departement' => $this->propriete->region->nom,
               // 'departementid' => $this->propriete->region->id,

                'region' => $this->propriete->region->nom,
               // 'regionid' => $this->propriete->region->id,
            ],
            'localisation' => [
                'longtitude'=>$this->propriete->longitude,
                'latitude'=>$this->propriete->latitude
            ],
            'proprietaire' => [

                'telephone' => $this->propriete->proprietaire->user->telephone,
            ],


        ];
    }
}

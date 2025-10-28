<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandeLocataireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
           // 'id' => $this->id,
            'date_demande' => $this->date_demande,
            'logement' => [
                'titre' => $this->logement->propriete->titre, // adapte selon champs de ta table
                'numero'=> $this->logement->numero,
                'adresse' => $this->logement->propriete->adresse,
            ],
            'bailleur' => [
                'nom' => $this->proprietaire->user->nom,
                'prenom'=>$this->proprietaire->user->prenom,
                'telephone' => $this->proprietaire->user->telephone, // à condition d’avoir ce champ
            ],
        ];
    }
}

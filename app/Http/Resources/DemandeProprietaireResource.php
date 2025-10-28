<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandeProprietaireResource extends JsonResource
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
            'locataire' => [
                'nom' => $this->locataire->user->nom,
                'prenom'=>$this->Locataire->user->prenom,
                'telephone' => $this->Locataire->user->telephone, // à condition d’avoir ce champ
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementLocataireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'typelogement' => $this->typelogement,
            'prix_loyer' => $this->prix_loyer,
            'superficie' => $this->superficie,
            'meuble' => $this->meuble,
            'etat' => $this->etat,
            'propriete' => [
                'id' => $this->propriete?->id,
                'titre' => $this->propriete?->titre,
                'adresse' => $this->propriete?->adresse,
            ],
        ];
    }
}

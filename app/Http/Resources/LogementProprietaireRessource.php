<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementProprietaireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            
            'type' => strtolower($this->typelogement), // ✅ Renvoie en minuscule (ex: "appartement")
            
            'nombre_pieces' => $this->nombre_pieces,
            'superficie' => $this->superficie,
            'meuble' => (bool) $this->meuble,
            'etat' => strtolower($this->etat), // ✅ Renvoie en minuscule (ex: "bon")
            'description' => $this->description,
            
            'loyer_mensuel' => $this->prix_loyer,
            'prix_loyer' => $this->prix_loyer, // Alias au cas où
            
            'statut_occupe' => $this->statut_occupe, // "disponible" ou "occupe"
            'disponible' => $this->statut_occupe === 'disponible', // ✅ Boolean pour Flutter
            
            'statut_publication' => $this->statut_publication,
            'propriete_id' => $this->propriete_id,
            
            'propriete' => [
                'adresse' => $this->propriete->adresse ?? '',
                'commune' => $this->propriete->commune->nom ?? '',
               // 'ville' => $this->propriete->commune->nom ?? '',
               // 'region' => $this->propriete->region->nom ?? '',
            ],
            
            // ✅ CORRECTION : URL complète de la photo principale
            'photo_principale' => $this->photos->where('principale', true)->first()
                ? $this->photos->where('principale', true)->first()->url_complete
                : ($this->photos->first() ? $this->photos->first()->url_complete : null),
            
            // ✅ CORRECTION : URLs complètes de toutes les photos
            'photos' => $this->photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->url_complete, // ✅ URL complète grâce à l'accessor
                    'est_principale' => (bool) $photo->principale,
                ];
            }),
        ];
    }
}

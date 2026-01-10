<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementProprietaireRessource extends JsonResource
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
        'numero_porte' => $this->numero, // Flutter attend 'numero_porte' ou 'numero' (on adaptera Flutter)

        // ⚠️ Envoie les données BRUTES pour que l'appli mobile puisse les traiter
        'typelogement' => ucfirst($this->logement), // Flutter attend 'typelogement'
        
        'nombre_pieces' => $this->nombre_pieces, // Envoie le CHIFFRE (ex: 3), pas "F3"
        
        'superficie' => $this->superficie, // Envoie le CHIFFRE (ex: 150), pas "150 m2"
        
        'meuble' => (bool) $this->meuble,
        'etat' => ucfirst($this->etat),
        'description' => $this->description,

        'loyer_mensuel' => $this->prix_loyer, // Flutter attend 'loyer_mensuel'

        'statut_occupe' => $this->statut_occupe,
        'statut_publication' => $this->statut_publication,

        // 👇 TRES IMPORTANT POUR LA SUPPRESSION
        'propriete_id' => $this->propriete_id, 

        'propriete' => [
            'adresse' => $this->propriete->adresse,
            'commune' => $this->propriete->commune->nom,
            'ville' => $this->propriete->commune->nom, // Ajout pour éviter null
            'departement' => $this->propriete->region->nom,
            'region' => $this->propriete->region->nom,
        ],

        // 🔹 NOUVEAU : photo principale et liste
       'photo_principale' => $this->photos->first()
            ? Storage::url($this->photos->first()->url)  // ✅ ici
            : null,

        'photos' => $this->photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'url' => Storage::url($photo->url),        // ✅ ici
            ];
        }),
    ];
}

}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogementProprietaireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. CALCULS PRÉALABLES
        // ---------------------
        
        // Titre (F3, F4...)
        $titre_affiche = '';
        if (strtolower($this->typelogement) === 'studio') {
            $titre_affiche = 'Studio';
        } else {
            $titre_affiche = ucfirst($this->typelogement) . ' F' . ($this->nombre_chambres + 1);
        }

        

        


        // 2. RETOUR DU JSON
        // -----------------
        return [
            'id' => $this->id,
            'identifiant' => $this->numero,
            'titre_affiche' => $titre_affiche, // Ex: "Appartement F3"

            'type' => strtolower($this->typelogement),
            'superficie' => (float) $this->superficie,
            'meuble' => (bool) $this->meuble,
            'etat' => strtolower($this->etat),
            'description' => $this->description .' '. $this->propriete->description,
            'nombre_pieces' => "F". ($this->nombre_chambres + 1),

            // --- SECTION FINANCIÈRE CALCULÉE (AUTOMATIQUE) ---
           
                'loyer_mensuel' => $this->prix_loyer,
            
            // ------------------------------------------------

            'statut_occupe' => $this->statut_occupe,
            'statut_publication' => $this->statut_publication,
            
            
                'id_propriete' => $this->propriete_id,
                'adresse' => $this->propriete->adresse ?? '',
                'commune' => $this->propriete->commune->nom ?? '',
            

            
                'chambres' => $this->nombre_chambres,
                'sdb' => $this->nombre_salles_de_bain,
            

            'photo_principale' => $this->photos->where('principale', true)->first()
                ? $this->photos->where('principale', true)->first()->url_complete
                : ($this->photos->first() ? $this->photos->first()->url_complete : null),

            'photos' => $this->photos->map(function($photo) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->url_complete, // Assure-toi que cet accessor existe (voir message précédent)
                    'est_principale' => (bool) $photo->principale,
                ];
            }),
        ];
    }
}

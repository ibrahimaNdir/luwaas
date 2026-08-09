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
        $profil = app(\App\Services\ScoreLocataireService::class)->getProfilFiabilite($this->locataire);

        return [
            // ✅ CHAMPS ESSENTIELS (décommentés et ajoutés)
            'id' => $this->id,
            'logement_id' => $this->logement_id,
            'locataire_id' => $this->locataire_id,
            'proprietaire_id' => $this->proprietaire_id,
            'status' => $this->status,
            'date_demande' => $this->date_demande,

           
            
            // ✅ RELATIONS
            'logement' => [
                'id' => $this->logement->id,
                'titre' => $this->logement->propriete->titre,
                'numero' => $this->logement->numero,
                'adresse' => $this->logement->propriete->adresse,
                'prix' => $this->logement->prix_loyer,
                'photo_principale' => $this->logement->photos->where('principale', true)->first()
                    ? $this->logement->photos->where('principale', true)->first()->url_complete
                    : ($this->logement->photos->first() ? $this->logement->photos->first()->url_complete : null),

                'photos' => $this->logement->photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => $photo->url_complete,
                        'est_principale' => (bool) $photo->principale,
                    ];
                }), 
                'statut' => $this->statut,
            ],
            
            'locataire' => [
                'id' => $this->locataire->user->id,
                'nom' => $this->locataire->user->nom,
                'prenom' => $this->locataire->user->prenom,
                'telephone' => $this->locataire->user->telephone,
                'email' => $this->locataire->user->email,
                // NOUVEAU : Score de fiabilité
                'score_fiabilite' => $this->locataire->score_fiabilite,
                'score_label' => $this->locataire->scoreLabel(),
                'total_paiements_en_ligne' => $this->locataire->total_paiements_en_ligne,
                'total_paiements_en_retard' => $this->locataire->total_paiements_en_retard,
                
                // Meca #1 : Protection contre la manipulation et Cold start
                'a_historique' => $profil['a_historique'] ?? false,
                'ratio_certification' => $profil['ratio_certification'] ?? 0,
                'total_paiements_payes' => $profil['total_paiements_payes'] ?? 0,
            ],
        ];
    }
}
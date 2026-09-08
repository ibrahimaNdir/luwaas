<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DemandeAvecScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $totalPaiements = $this->locataire->total_paiements_payes ?? 0;
        $paiementsEnLigne = $this->locataire->total_paiements_en_ligne ?? 0;
        $ratioCertification = $totalPaiements > 0 ? round(($paiementsEnLigne / $totalPaiements) * 100) : 0;

        $paiementsATemps = $this->locataire->total_paiements_a_temps ?? 0;
        $tauxPonctualite = $paiementsEnLigne > 0 ? round(($paiementsATemps / $paiementsEnLigne) * 100) : null;

        return [
            'id' => $this->id,
            'logement' => [
                'id' => $this->logement->id,
                'titre' => $this->logement->titre,
                'prix_loyer' => $this->logement->prix_loyer,
                'nombre_chambres' => $this->logement->nombre_chambres,
                'nombre_salles_de_bain' => $this->logement->nombre_salles_de_bain,
            ],
            'locataire' => [
                'id' => $this->locataire->id,
                'prenom' => $this->locataire->prenom,
                'nom' => $this->locataire->nom,
                'telephone' => $this->locataire->telephone,
                'email' => $this->locataire->email,
                // Score fiabilité
                'score_fiabilite' => $this->locataire->score_fiabilite ?? null,
                'score_label' => $this->locataire->score_fiabilite !== null
                    ? $this->locataire->scoreLabel()
                    : 'Nouveau',
                'total_paiements_payes' => $this->locataire->total_paiements_payes ?? 0,
                'total_paiements_en_ligne' => $this->locataire->total_paiements_en_ligne ?? 0,
                'ratio_certification' => $ratioCertification,
                'taux_ponctualite' => $tauxPonctualite,
            ],
            'date_demande' => $this->date_demande,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementProprietaireRessource extends JsonResource
{
    public function toArray($request) {
        return [

            // 'id' => $this->id,
            'logement' => [
                'id' => $this->id,
                'Type de paiement' => $this->transactions->first()?->mode_paiement ?? 'Non défini',
                'locataire'   => $this->bail->locataire->user->prenom . ' ' . $this->bail->locataire->user->nom,
                'date_echeance'  =>$this->date_echeance ,
                'montant_attendu'   =>$this->montant_attendu,
                'montant_paye'   =>$this->montant_paye,
                'periode' => $this->periode,
               

                'date_paiement' =>$this->date_paiement,
                'statut' =>$this->statut,
                'numero' => $this->bail->logement->numero,
                'id_bail' => $this->bail_id,
                // Ajoute d'autres champs utiles selon ton modèle logement
            ],



        ];
    }


}

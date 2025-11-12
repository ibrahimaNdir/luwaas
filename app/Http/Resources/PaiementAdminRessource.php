<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementAdminRessource extends JsonResource
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

                      'locataire' => [
                          // 'id'         => $this->locataire->id,
                          'nom'        => $this->locataire->user->nom,
                          'telephone'  => $this->locataire->user->telephone,
                          'prenom'  => $this->locataire->user->prenom

                      ],
                      'bailleur'=>[
                          'nom'        => $this->bail->logement->propriete->proprietaire->user->nom,
                          'telephone'  => $this->bail->logement->propriete->proprietaire->user->telephone,
                          'prenom'  => $this->bail->logement->propriete->proprietaire->user->prenom,
                      ],

                      'paiement' => [
                          //  'id'         => $this->id,
                          'titre'      => $this->periode,
                          'statut'    => $this->statut,
                          'date '      =>$this->date_echeance ,
                          'montant'   =>$this->montant_attendu


                          // Ajoute d'autres champs utiles selon ton modèle logement
                      ],




            ];



    }
}

<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BailAdminResource extends JsonResource
{
    private $logement;

    public function toArray($request)
    {
        return [
            'logement' => [
                'titre'   => $this->logement->propriete->titre,
                'adresse'  => $this->logement->propriete->adresse,
                'type'     => $this->logement->type,
                'numero'   => $this->logement->numero,
            ],
            'locataire' => [
                'nom'       => $this->locataire->user->nom,
                'telephone' => $this->locataire->user->telephone,
                'prenom'    => $this->locataire->user->prenom,
            ],
            'bailleur' => [
                'nom'       => $this->logement->propriete->proprietaire->user->nom,
                'telephone' => $this->logement->propriete->proprietaire->user->telephone,
                'prenom'    => $this->logement->propriete->proprietaire->user->prenom,
            ],
            'montant_loyer'      => $this->montant_loyer,
            'charges_mensuelles' => $this->charges_mensuelles,
            'caution' => [
                'total'          => $this->montant_caution_total,
                'paye_signature' => $this->montant_caution_signature,
                'reste_a_etaler' => $this->montant_caution_etale,
                'mensualite'     => $this->mensualite_caution,
                'mois_restants'  => $this->mois_etalement_restants,
            ],
            'date_debut'  => Carbon::parse($this->date_debut)->format('Y-m-d'),
            'date_fin'    => Carbon::parse($this->date_fin)->format('Y-m-d'),
            'renouvellement' => $this->renouvellement_automatique,
            'statut'      => $this->statut_dynamique,
        ];
    }
}

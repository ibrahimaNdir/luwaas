<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BailProprietaireRessource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement' => [
                'titre'  => $this->logement->propriete->titre,
                'adresse' => $this->logement->propriete->adresse,
                'type'   => $this->logement->typelogement,
                'numero' => $this->logement->numero,
            ],
            'locataire' => [
                'nom'      => $this->locataire->user->nom,
                'prenom'   => $this->locataire->user->prenom,
                'telephone' => $this->locataire->user->telephone,
            ],
            'montant_loyer'        => $this->montant_loyer,
            'charges_mensuelles'   => $this->charges_mensuelles,
            'caution'              => $this->montant_caution_total, // ✅ corrigé
            'cautions_a_payer'     => $this->montant_caution_total, // ✅ restauré pour Flutter
            'date_debut'           => Carbon::parse($this->date_debut)->format('Y-m-d'),
            'date_fin'             => Carbon::parse($this->date_fin)->format('Y-m-d'),
            'jour_echeance'        => $this->jour_echeance,
            'renouvellement'       => $this->renouvellement_automatique,
            'statut'               => $this->statut_dynamique,
        ];
    }
}
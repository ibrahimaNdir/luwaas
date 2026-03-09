<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BailLocataireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $estActif = $this->statut === 'actif';

        $base = [
            'id'            => $this->id,
            'statut'        => $this->statut,
            'logement'      => [
                'titre'   => $this->logement->propriete->titre ?? null,
                'adresse' => $this->logement->propriete->adresse ?? null,
                'type'    => $this->logement->propriete->type ?? null,
                'numero'  => $this->logement->numero ?? null,
            ],
            'montant_loyer' => $this->montant_loyer,
            'date_debut'    => $this->date_debut,
            'date_fin'      => $this->date_fin,
        ];

        // ❌ Bail pas encore payé → infos de base seulement
        if (!$estActif) {
            return array_merge($base, [
                'message'  => 'Veuillez payer la caution pour activer votre bail.',
                'paiement' => $this->paiements
                    ->where('type', 'signature')
                    ->first()?->only(['id', 'montant_attendu', 'statut']),
            ]);
        }

        // ✅ Bail actif → toutes les infos
        return array_merge($base, [
            'bailleur' => [
                'prenom'    => $this->logement->propriete->bailleur->prenom ?? null,
                'nom'       => $this->logement->propriete->bailleur->nom ?? null,
                'telephone' => $this->logement->propriete->bailleur->telephone ?? null,
            ],
            'charges_mensuelles'        => $this->charges_mensuelles,
            'caution'                   => $this->caution,
            'jour_echeance'             => $this->jour_echeance,
            'renouvellement_automatique' => $this->renouvellement_automatique,
            'historique_paiements'      => $this->paiements,
        ]);
    }
}

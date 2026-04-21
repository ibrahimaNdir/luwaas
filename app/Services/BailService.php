<?php

namespace App\Services;

use App\Events\BailCree;
use App\Models\Bail;
use App\Models\Demande;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BailService
{
    /**
     * Vérifie que le propriétaire peut créer un bail depuis cette demande.
     */
    public function validerDemande(Demande $demande, int $proprietaireId): ?string
    {
        if ($demande->status !== 'acceptee') {
            return 'La demande doit être acceptée avant de créer un bail.';
        }

        if ($demande->proprietaire_id !== $proprietaireId) {
            return 'Cette demande ne vous concerne pas.';
        }

        if ($demande->logement->statut_occupe !== 'disponible') {
            return 'Ce logement n\'est plus disponible.';
        }

        $bailExistant = Bail::where('logement_id', $demande->logement_id)
            ->whereIn('statut', ['en_attente_paiement', 'actif'])
            ->exists();

        if ($bailExistant) {
            return 'Un bail actif existe déjà pour ce logement.';
        }

        return null;
    }

    /**
     * Crée le bail + le paiement de signature.
     */
    public function creerBail(Demande $demande, array $data): Bail
    {
        $montantCaution = $data['montant_loyer'] * $data['nombre_mois_caution'];
        $montantTotalSignature = $montantCaution + $data['montant_loyer'];
        $periode = Carbon::parse($data['date_debut'])->isoFormat('MMMM YYYY');

        $bail = Bail::create([
            'logement_id'                => $demande->logement_id,
            'locataire_id'               => $demande->locataire_id,
            'demande_id'                 => $demande->id,
            'montant_loyer'              => $data['montant_loyer'],
            'charges_mensuelles'         => $data['charges_mensuelles'],
            'nombre_mois_caution'        => $data['nombre_mois_caution'],
            'montant_caution_total'      => $montantCaution,
            'date_debut'                 => $data['date_debut'],
            'date_fin'                   => $data['date_fin'],
            'jour_echeance'              => $data['jour_echeance'],
            'renouvellement_automatique' => $data['renouvellement_automatique'],
            'conditions_speciales'       => $data['conditions_speciales'] ?? null,
            'statut'                     => 'en_attente_paiement',
        ]);

        Paiement::create([
            'locataire_id'    => $demande->locataire_id,
            'bail_id'         => $bail->id,
            'type'            => 'signature',
            'montant_attendu' => $montantTotalSignature,
            'montant_paye'    => 0,
            'montant_restant' => $montantTotalSignature,
            'statut'          => 'impayé',
            'date_echeance'   => now()->addDays(7),
            'periode'         => $periode,
        ]);

        $demande->update(['status' => 'bail_cree']);

        event(new BailCree($bail));

        Log::info("✅ Bail créé : ID {$bail->id} depuis demande {$demande->id}");

        return $bail;
    }

    /**
     * Vérifie si l'utilisateur est locataire ou bailleur du bail.
     */
    public function verifierAcces(Bail $bail, int $userId): bool
    {
        $isLocataire = $bail->locataire?->user_id === $userId;
        $isBailleur  = $bail->logement->propriete->proprietaire?->user_id === $userId;

        return $isLocataire || $isBailleur;
    }
}
<?php

namespace App\Services;

use App\Models\Demande;
use App\Models\Logement;

class DemandeService
{
    // ═══════════════════════════════════════════
    // CRÉATION
    // ═══════════════════════════════════════════

    /**
     * Vérifie qu'un locataire peut créer une demande pour ce logement.
     * Retourne un message d'erreur ou null si OK.
     */
    public function validerCreation(Logement $logement, int $locataireId): ?array
    {
        if ($logement->statut_occupe !== 'disponible') {
            return ['message' => 'Ce logement n\'est plus disponible à la location.', 'status' => 422];
        }

        $existeDeja = Demande::where('logement_id', $logement->id)
            ->where('locataire_id', $locataireId)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->exists();

        if ($existeDeja) {
            return ['message' => 'Vous avez déjà une demande en cours pour ce logement.', 'status' => 409];
        }

        return null;
    }

     public function creer(Logement $logement, int $locataireId): Demande
    {
        $demande = Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataireId,
            'proprietaire_id' => $logement->propriete->proprietaire->id,
            'date_demande'    => now(),
            'status'          => 'en_attente',
        ]);

        event(new \App\Events\DemandeLogementRecue($demande));

        return $demande;
    }

    // ═══════════════════════════════════════════
    // ACTIONS PROPRIÉTAIRE
    // ═══════════════════════════════════════════

    /**
     * Vérifie que le propriétaire est bien owner de la demande.
     */
    public function verifierProprietaire(Demande $demande, int $proprietaireId): bool
    {
        return $demande->proprietaire_id === $proprietaireId;
    }

    public function accepter(Demande $demande): void
    {
        $demande->update(['status' => 'acceptee']);
        event(new \App\Events\DemandeAcceptee($demande));
    }

    public function refuser(Demande $demande): void
    {
        $demande->update(['status' => 'refusee']);
        event(new \App\Events\DemandeRefusee($demande));
    }

    // ═══════════════════════════════════════════
    // ACTIONS LOCATAIRE
    // ═══════════════════════════════════════════

    /**
     * Vérifie que le locataire est bien owner de la demande.
     */
    public function verifierLocataire(Demande $demande, int $locataireId): bool
    {
        return $demande->locataire_id === $locataireId;
    }

    public function annuler(Demande $demande): void
    {
        $ancienStatus = $demande->status;
        $demande->update(['status' => 'annulee']);
        event(new \App\Events\DemandeAnnulee($demande, $ancienStatus));
    }
}
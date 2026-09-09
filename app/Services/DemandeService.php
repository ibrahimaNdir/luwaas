<?php

namespace App\Services;

use App\Models\Demande;
use App\Models\Logement;

class DemandeService
{
    // ═══════════════════════════════════════════
    // CRÉATION
    // ═══════════════════════════════════════════

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

    public function verifierProprietaire(Demande $demande, int $proprietaireId): bool
    {
        return $demande->proprietaire_id === $proprietaireId;
    }

    /**
     * Accepte la demande. Ne touche pas au logement ni aux autres demandes —
     * plusieurs demandes peuvent être acceptées en parallèle tant qu'aucun
     * bail n'est créé (voir BailService::creerBail pour le refus en cascade).
     */
    public function accepter(Demande $demande): void
    {
        $demande->update([
            'status'           => 'acceptee',
            'date_acceptation' => now(),
        ]);

        event(new \App\Events\DemandeAcceptee($demande));
    }

    public function refuser(Demande $demande, string $motif = null): void
    {
        $updateData = [
            'status'    => 'refusee',
            'date_refus' => now(),
        ];

        // Seulement ajouter le motif si fourni (optionnel)
        if ($motif !== null) {
            $updateData['motif_refus'] = $motif;
        }

        $demande->update($updateData);

        event(new \App\Events\DemandeRefusee($demande));
    }

    // ═══════════════════════════════════════════
    // ACTIONS LOCATAIRE
    // ═══════════════════════════════════════════

    public function verifierLocataire(Demande $demande, int $locataireId): bool
    {
        return $demande->locataire_id === $locataireId;
    }

    /**
     * Annule la demande. Ne touche pas au logement — il ne passe jamais
     * automatiquement à "reserve", donc rien à remettre à "disponible".
     */
    public function annuler(Demande $demande): void
    {
        $ancienStatus = $demande->status;

        $demande->update(['status' => 'annulee']);

        event(new \App\Events\DemandeAnnulee($demande, $ancienStatus));
    }


    public function marquerNonAboutie(Demande $demande): void
    {
        if ($demande->status !== 'acceptee') {
            return;
        }

        $demande->update([
            'status'           => 'non_aboutie',
            'date_non_aboutie' => now(),
        ]);

        event(new \App\Events\DemandeNonAboutie($demande));
    }

    public function refuserAutomatiquementApresBail(int $logementId, int $bailCreeDemandeId): void
    {
        $demandes = Demande::where('logement_id', $logementId)
            ->where('id', '!=', $bailCreeDemandeId)
            ->whereIn('status', ['en_attente', 'acceptee'])
            ->get();

        foreach ($demandes as $demande) {
            $demande->update([
                'status'      => 'refusee',
                'date_refus'   => now(),
            ]);

            event(new \App\Events\DemandeRefusee($demande));
        }
    }
}

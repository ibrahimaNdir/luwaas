<?php

namespace App\Services;

use App\Models\Locataire;
use App\Models\Paiement;

class ScoreLocataireService
{
    // Pondérations du score
    private const BONUS_A_TEMPS  =  2;
    private const MALUS_RETARD   = 10;
    private const SCORE_MIN      =  0;
    private const SCORE_MAX      = 100;

    /**
     * Met à jour les compteurs et recalcule le score après un paiement EN LIGNE.
     * N'est JAMAIS appelé pour un paiement manuel.
     *
     * @param Locataire $locataire
     * @param Paiement  $paiement     Le paiement qui vient d'être validé
     */
    public function mettreAJourScore(Locataire $locataire, Paiement $paiement): void
    {
        // Déterminer si le paiement était en retard
        $estEnRetard = $paiement->date_echeance !== null
            && now()->isAfter($paiement->date_echeance);

        // Mise à jour des compteurs
        $locataire->increment('total_paiements_en_ligne');

        if ($estEnRetard) {
            $locataire->increment('total_paiements_en_retard');
        } else {
            $locataire->increment('total_paiements_a_temps');
        }

        // Reload pour avoir les valeurs à jour
        $locataire->refresh();

        // Recalcul du score
        $score = self::SCORE_MAX
            + ($locataire->total_paiements_a_temps * self::BONUS_A_TEMPS)
            - ($locataire->total_paiements_en_retard * self::MALUS_RETARD);

        $score = max(self::SCORE_MIN, min(self::SCORE_MAX, $score));

        $locataire->update(['score_fiabilite' => $score]);
    }

    public function getProfilFiabilite(Locataire $locataire): array
    {
        // Utilise la valeur pré-chargée via withCount si disponible, sinon exécute la requête
        if (array_key_exists('total_paiements_payes', $locataire->getAttributes())) {
            $totalPaiementsPayes = $locataire->total_paiements_payes;
        } else {
            $totalPaiementsPayes = Paiement::where('locataire_id', $locataire->id)
                ->where('statut', 'payé')
                ->count();
        }
            
        $totalPaiementsEnLigne = $locataire->total_paiements_en_ligne;
        $totalPaiementsManuels = max(0, $totalPaiementsPayes - $totalPaiementsEnLigne);

        $ratioCertification = $totalPaiementsPayes > 0
            ? round(($totalPaiementsEnLigne / $totalPaiementsPayes) * 100)
            : 0;
        $aHistorique = $totalPaiementsPayes > 0;

        return [
            'a_historique'               => $aHistorique,
            'score'                      => $locataire->score_fiabilite,
            'label'                      => $aHistorique ? $locataire->scoreLabel() : 'Nouveau',
            'total_paiements_payes'      => $totalPaiementsPayes,
            'total_paiements_en_ligne'   => $totalPaiementsEnLigne,
            'total_paiements_manuels'    => $totalPaiementsManuels,
            'ratio_certification'        => $ratioCertification,
            'total_paiements_a_temps'    => $locataire->total_paiements_a_temps,
            'total_paiements_en_retard'  => $locataire->total_paiements_en_retard,
            'taux_ponctualite'           => $totalPaiementsEnLigne > 0
                ? round(($locataire->total_paiements_a_temps / $totalPaiementsEnLigne) * 100)
                : null,
            'note'                       => $totalPaiementsEnLigne === 0
                ? 'Aucun paiement via Luwaas enregistré. Le score sera alimenté dès le premier paiement en ligne.'
                : ($ratioCertification < 50 ? 'Attention : la majorité des paiements de ce locataire ne sont pas certifiés Luwaas.' : null),
        ];
    }
}

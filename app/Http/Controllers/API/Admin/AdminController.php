<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proprietaire;
use App\Models\Locataire;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Stats globales du SaaS
     */
    public function stats()
    {
        $now = now();

        // ── Propriétaires ──────────────────────────────────────
        $totalProprietaires      = Proprietaire::count();
        $proprietairesActifs     = Proprietaire::where('is_actif', true)->count();

        // Nouveaux ce mois-ci
        $nouveauxCeMois          = Proprietaire::whereMonth('created_at', $now->month)
                                               ->whereYear('created_at', $now->year)
                                               ->count();

        // Nouveaux le mois dernier (pour calculer la croissance)
        $nouveauxMoisDernier     = Proprietaire::whereMonth('created_at', $now->copy()->subMonth()->month)
                                               ->whereYear('created_at', $now->copy()->subMonth()->year)
                                               ->count();

        // Churn rate : propriétaires annulés ce mois / total début de mois
        $annulesCeMois           = Proprietaire::where('subscription_status', 'cancelled')
                                               ->whereMonth('cancelled_at', $now->month)
                                               ->whereYear('cancelled_at', $now->year)
                                               ->count();

        $churnRate = $proprietairesActifs > 0
            ? round(($annulesCeMois / ($proprietairesActifs + $annulesCeMois)) * 100, 2)
            : 0;

        // ── Locataires ─────────────────────────────────────────
        $totalLocataires         = Locataire::count();

        // ── Transactions ───────────────────────────────────────
        $transactionsCeMois      = Transaction::where('statut', 'success')
                                              ->whereMonth('date_transaction', $now->month)
                                              ->whereYear('date_transaction', $now->year);

        // MRR = total des transactions réussies ce mois
        $mrr                     = (clone $transactionsCeMois)->sum('montant');
        $nombreTransactions      = (clone $transactionsCeMois)->count();

        // MRR mois dernier (pour comparer)
        $mrrMoisDernier          = Transaction::where('statut', 'success')
                                              ->whereMonth('date_transaction', $now->copy()->subMonth()->month)
                                              ->whereYear('date_transaction', $now->copy()->subMonth()->year)
                                              ->sum('montant');

        // Croissance MRR en %
        $croissanceMrr = $mrrMoisDernier > 0
            ? round((($mrr - $mrrMoisDernier) / $mrrMoisDernier) * 100, 2)
            : 0;

        // ── Paiements ──────────────────────────────────────────
        $paiementsEnAttente      = Paiement::where('statut', 'en_attente')->count();
        $paiementsEnRetard       = Paiement::where('statut', 'en_retard')->count();

        // ── Abonnements par statut ─────────────────────────────
        $abonnementsActifs       = Proprietaire::where('subscription_status', 'active')->count();
        $abonnementsEssai        = Proprietaire::where('subscription_status', 'trial')->count();
        $abonnementsExpires      = Proprietaire::where('subscription_status', 'expired')->count();

        return response()->json([
            'success' => true,
            'data' => [

                // KPIs principaux
                'mrr'                    => $mrr,
                'mrr_mois_dernier'       => $mrrMoisDernier,
                'croissance_mrr'         => $croissanceMrr, // en %

                // Propriétaires
                'total_proprietaires'    => $totalProprietaires,
                'proprietaires_actifs'   => $proprietairesActifs,
                'nouveaux_ce_mois'       => $nouveauxCeMois,
                'nouveaux_mois_dernier'  => $nouveauxMoisDernier,
                'churn_rate'             => $churnRate, // en %

                // Locataires
                'total_locataires'       => $totalLocataires,

                // Transactions
                'transactions_ce_mois'   => $nombreTransactions,

                // Paiements
                'paiements_en_attente'   => $paiementsEnAttente,
                'paiements_en_retard'    => $paiementsEnRetard,

                // Abonnements
                'abonnements_actifs'     => $abonnementsActifs,
                'abonnements_essai'      => $abonnementsEssai,
                'abonnements_expires'    => $abonnementsExpires,

                // Meta
                'genere_le'              => $now->toDateTimeString(),
            ]
        ]);
    }
}
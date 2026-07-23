<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proprietaire;
use App\Models\Locataire;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;
use App\Models\Subscription;

class AdminController extends Controller
{
    /**
     * Stats globales du SaaS
     */
   public function stats()
{
    $now = now();

    // ── Propriétaires ──────────────────────────
    $totalProprietaires  = Proprietaire::count();
    $proprietairesActifs = Proprietaire::where('is_actif', true)->count();
    $nouveauxCeMois      = Proprietaire::whereMonth('created_at', $now->month)
                            ->whereYear('created_at', $now->year)->count();

    // ── Abonnements par statut ──────────────────
    $abonnementsGratuit  = Proprietaire::where('subscription_status', 'gratuit')->count();
    $abonnementsPro      = Proprietaire::where('subscription_status', 'active')
                            ->where('plan', 'pro')->count();
    $abonnementsExpires  = Proprietaire::where('subscription_status', 'expired')->count();
    $annulesCeMois       = Proprietaire::where('subscription_status', 'cancelled')
                            ->whereMonth('cancelled_at', $now->month)
                            ->whereYear('cancelled_at', $now->year)->count();

    $churnRate = $proprietairesActifs > 0
        ? round(($annulesCeMois / ($proprietairesActifs + $annulesCeMois)) * 100, 2)
        : 0;

    // ── MRR LUWAAS (abonnements Pro payés) ──────
    $mrrLuwaas = Subscription::where('status', 'active')
                    ->whereMonth('starts_at', $now->month)
                    ->whereYear('starts_at', $now->year)
                    ->sum('amount');

    $mrrMoisDernier = Subscription::where('status', 'active')
                        ->whereMonth('starts_at', $now->copy()->subMonth()->month)
                        ->whereYear('starts_at', $now->copy()->subMonth()->year)
                        ->sum('amount');

    $croissanceMrr = $mrrMoisDernier > 0
        ? round((($mrrLuwaas - $mrrMoisDernier) / $mrrMoisDernier) * 100, 2)
        : 0;

    // ── Paiements loyers ────────────────────────
    $paiementsEnAttente = Paiement::where('statut', 'en_attente')->count();
    $paiementsEnRetard  = Paiement::where('statut', 'en_retard')->count();

    // ── Locataires ──────────────────────────────
    $totalLocataires = Locataire::count();

    return response()->json([
        'success' => true,
        'data' => [
            // MRR Luwaas
            'mrr'                   => $mrrLuwaas,
            'mrr_mois_dernier'      => $mrrMoisDernier,
            'croissance_mrr'        => $croissanceMrr,

            // Propriétaires
            'total_proprietaires'   => $totalProprietaires,
            'proprietaires_actifs'  => $proprietairesActifs,
            'nouveaux_ce_mois'      => $nouveauxCeMois,
            'churn_rate'            => $churnRate,

            // Abonnements
            'abonnements_gratuit'   => $abonnementsGratuit,
            'abonnements_pro'       => $abonnementsPro,
            'abonnements_expires'   => $abonnementsExpires,

            // Locataires
            'total_locataires'      => $totalLocataires,

            // Loyers
            'paiements_en_attente'  => $paiementsEnAttente,
            'paiements_en_retard'   => $paiementsEnRetard,

            'genere_le'             => $now->toDateTimeString(),
        ]
    ]);
}
}
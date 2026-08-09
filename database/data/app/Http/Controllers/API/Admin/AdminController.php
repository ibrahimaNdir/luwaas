<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proprietaire;
use App\Models\Locataire;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;

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
    $abonnementsGratuit  = Proprietaire::where('subscription_status', 'free_trial')->count();
    $abonnementsPro      = Proprietaire::where('subscription_status', 'active')
                            ->where('plan', 'pro')->count();
    $abonnementsExpires  = Proprietaire::where('subscription_status', 'expired')->count();
    $annulesCeMois       = Proprietaire::where('subscription_status', 'cancelled')
                            ->whereMonth('cancelled_at', $now->month)
                            ->whereYear('cancelled_at', $now->year)->count();

    $churnRate = $proprietairesActifs > 0
        ? round(($annulesCeMois / ($proprietairesActifs + $annulesCeMois)) * 100, 2)
        : 0;

    // ── MRR LUWAAS (abonnements Pro actifs, normalisés en mensuel) ──────
    $mrrLuwaas = $this->calculateMrr($now);

    $finMoisDernier = $now->copy()->subMonth()->endOfMonth();
    $mrrMoisDernier = $this->calculateMrr($finMoisDernier);

    $croissanceMrr = $mrrMoisDernier > 0
        ? round((($mrrLuwaas - $mrrMoisDernier) / $mrrMoisDernier) * 100, 2)
        : 0;

    // ── Paiements loyers ────────────────────────
    $paiementsEnAttente = Paiement::whereIn('statut', ['impayé', 'partiel'])->count();
    $paiementsEnRetard  = Paiement::where('statut', 'en_retard')->count();

    // ── Locataires ──────────────────────────────
    $totalLocataires = Locataire::count();

    // ── Utilisateurs en ligne (dernières 5 minutes) ──
    $usersEnLigneCount = $this->getOnlineUsersCount();

    return response()->json([
        'success' => true,
        'data' => [
            // MRR Luwaas
            'mrr'                   => $mrrLuwaas,
            'mrr_mois_dernier'      => $mrrMoisDernier,
            'croissance_mrr'        => $croissanceMrr,

            // Utilisateurs en ligne
            'users_en_ligne'        => $usersEnLigneCount,

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

    /**
     * Obtenir la liste et le nombre des utilisateurs actuellement en ligne
     */
    public function onlineUsers()
    {
        $userIds = $this->getOnlineUserIds();

        $users = \App\Models\User::whereIn('id', $userIds)
            ->select(['id', 'prenom', 'nom', 'email', 'telephone', 'user_type'])
            ->get();

        return response()->json([
            'success'     => true,
            'total_online' => count($users),
            'data'         => $users,
        ]);
    }

    private function getOnlineUsersCount(): int
    {
        return count($this->getOnlineUserIds());
    }

    private function getOnlineUserIds(): array
    {
        $registry = Cache::get('online_user_ids', []);
        $cutoff   = now()->subMinutes(5)->timestamp;

        return collect($registry)
            ->filter(fn ($timestamp) => $timestamp >= $cutoff)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * MRR = somme mensuelle des abonnements actifs à une date donnée.
     */
    private function calculateMrr(\Carbon\Carbon $at): float
    {
        return Subscription::whereIn('status', ['active', 'cancelled'])
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->with('plan')
            ->get()
            ->sum(function (Subscription $subscription) {
                $amount = (float) $subscription->amount;

                if ($subscription->plan?->billing_cycle === 'yearly') {
                    return $amount / 12;
                }

                return $amount;
            });
    }
}
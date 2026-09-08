<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\Demande;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Proprietaire;
use App\Models\Propriete;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProprietaireDashboardService
{

    // DASHBOARD

    public function dashboard(int $ownerId): array
    {
        $debutMois = Carbon::now()->startOfMonth();
        $finMois   = Carbon::now()->endOfMonth();

        // Get the proprietaire object to access subscription info
        $proprietaire = Proprietaire::findOrFail($ownerId);

        // Get subscription summary using the Subscribable trait
        $subscriptionSummary = $proprietaire->subscriptionSummary();

        // ───────────────────────────────────────────────────────────────────────
        // STATS TEMPS RÉEL (Propriétés & Logements)
        // ───────────────────────────────────────────────────────────────────────

        $totalProprietes = Propriete::where('proprietaire_id', $ownerId)->count();

        $statsLogements = Logement::whereHas(
            'propriete',
            fn($q) => $q->where('proprietaire_id', $ownerId)
        )
            ->select(
                DB::raw('COUNT(*) as total_logements'),
                DB::int'),
                DB::raw("SUM(CASE WHEN statut_occupe = 'occupe' THEN 1 ELSE 0 END) as total_occupe::int"),
                DB::raw("SUM(CASE WHEN statut_occupe = 'disponible' THEN 1 ELSE 0 END) as total_disponible::int")
            )
            ->first();

        $tauxOccupation = ($statsLogements->total_logements ?? 0) > 0
            ? round(($statsLogements->total_occupe / $statsLogements->total_logements) * 100, 1)
            : 0;

        // ───────────────────────────────────────────────────────────────────────
        // STATS DEMANDES & BAUX
        // ───────────────────────────────────────────────────────────────────────

        $demandesEnAttente = Demande::whereHas(
            'logement.propriete',
            fn($q) => $q->where('proprietaire_id', $ownerId)
        )
            ->where('status', 'en_attente')
            ->count();

        $bailsActifs = Bail::whereHas(
            'logement.propriete',
            fn($q) => $q->where('proprietaire_id', $ownerId)
        )
            ->where('statut', 'actif')
            ->count();

        // ───────────────────────────────────────────────────────────────────────
        // STATS PAIEMENTS - BASE RÉUTILISABLE
        // ───────────────────────────────────────────────────────────────────────

        $basePaiements = fn() => Paiement::whereHas(
            'bail.logement.propriete',
            fn($q) => $q->where('proprietaire_id', $ownerId)
        );

        // ✅ REVENUS DU MOIS (Montant payé corrigé)
        $revenusMois = $basePaiements()
            ->where('statut', 'payé')
            ->whereBetween('date_paiement', [$debutMois, $finMois])
            ->sum('montant_paye');

        // ✅ PAIEMENTS ATTENDUS
        $paiementsAttendus = $basePaiements()
            ->whereIn('statut', ['impayé', 'en_retard'])
            ->whereBetween('date_echeance', [$debutMois, $finMois])
            ->sum('montant_restant');

        // ✅ NOUVEAU: PAIEMENTS EN RETARD
        $paiementsEnRetard = $basePaiements()
            ->where('statut', 'en_retard')
            ->sum('montant_restant');

        // ✅ NOUVEAU: TAUX DE RECOUVREMENT (%)
        $totalARecouvrir = $basePaiements()
            ->whereBetween('date_echeance', [$debutMois, $finMois])
            ->sum('montant_attendu');

        $totalRecouvert = $basePaiements()
            ->where('statut', 'payé')
            ->whereBetween('date_paiement', [$debutMois, $finMois])
            ->sum('montant_paye');

        $tauxRecouvrement = $totalARecouvrir > 0
            ? round(($totalRecouvert / $totalARecouvrir) * 100, 1)
            : 0;

<<<<<<< HEAD
        // ✅ NOUVEAU: MÉCANISME #3 - PAIEMENTS MANUELS DU MOIS
        $loyersManuelsDuMois = $basePaiements()
            ->where('statut', 'payé')
            ->whereBetween('date_paiement', [$debutMois, $finMois])
            ->whereHas('transactions', function ($query) {
                $query->whereIn('mode_paiement', ['especes', 'wave_direct', 'virement', 'cheque']);
            })
            ->count();
 
=======
        // ───────────────────────────────────────────────────────────────────────
        // ABONNEMENT - INFOS D'ABONNEMENT POUR TABLEAU DE BORD
        // ───────────────────────────────────────────────────────────────────────

        $subscriptionMessage = $this->getSubscriptionMessage($proprietaire, $subscriptionSummary);

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        // ───────────────────────────────────────────────────────────────────────
        // RETOUR DES DONNÉES
        // ───────────────────────────────────────────────────────────────────────

        return [
            'stats_temps_reel' => [
                'total_proprietes'           => $totalProprietes,
                'total_logements'            => $statsLogements->total_logements ?? 0,
                'total_logements_occupe'     => $statsLogements->total_occupe ?? 0,
                'total_logements_disponible' => $statsLogements->total_disponible ?? 0,
                'taux_occupation'            => $tauxOccupation,
                'demandes_en_attente'        => $demandesEnAttente,
                'baux_actifs'                => $bailsActifs,
            ],
            'stats_mois_en_cours' => [
                'mois'                  => Carbon::now()->translatedFormat('F Y'),
                'revenus_recus'         => $revenusMois,
                'paiements_attendus'    => $paiementsAttendus,
                'revenus_potentiels'    => $revenusMois + $paiementsAttendus,
                'paiements_en_retard'   => $paiementsEnRetard,  
                'taux_recouvrement'     => $tauxRecouvrement,   
                'loyers_manuels_du_mois'=> $loyersManuelsDuMois, // ✅ NOUVEAU: Meca #3
                'afficher_alerte_retention' => $loyersManuelsDuMois > 0, // ✅ Facilite le frontend
            ],
            'subscription' => [
                'plan' => $subscriptionSummary['plan'],
                'status' => $subscriptionSummary['status'],
                'message' => $subscriptionMessage,
                'limits' => $subscriptionSummary['limits'],
                'is_pro' => $subscriptionSummary['is_pro'],
                'can_publish' => $subscriptionSummary['can_publish'],
            ],
        ];
    }

    /**
     * Generate the subscription message for dashboard display based on user's requirements
     *
     * @param App\Models\Proprietaire $proprietaire
     * @param array $subscriptionSummary
     * @return string
     */
    private function getSubscriptionMessage(Proprietaire $proprietaire, array $subscriptionSummary): string
    {
        $plan = $subscriptionSummary['plan'];
        $isPro = $subscriptionSummary['is_pro'];
        $status = $subscriptionSummary['status'];
        $endsAt = $subscriptionSummary['ends_at']; // Carbon instance or null
        $limits = $subscriptionSummary['limits'];

        // When on Starter: "Plan gratuit actif - 5 logements max"
        if ($plan === 'starter') {
            $maxPublications = $limits['publications_max'] ?? 5;
            return "Plan gratuit actif - {$maxPublications} logements max";
        }

        // When on Pro and active: "Plan Pro actif - Date de renouvellement : [date]"
        if ($plan === 'pro' && $isPro === true && $endsAt instanceof Carbon) {
            $renewalDate = $endsAt->translatedFormat('d F Y');
            return "Plan Pro actif - Date de renouvellement : {$renewalDate}";
        }

        // When Pro is in grace period (between expiration and cron processing):
        // "Votre abonnement Pro expirera bientôt - Renouvelez pour éviter le retour au plan gratuit"
        // This covers:
        // 1. Plan is pro but not active (expired or not paid) - grace period before cron processes
        // 2. Plan is pro and active but expiring soon (within 3 days) - advance warning
        if ($plan === 'pro') {
            // Check if we're in the warning period (3 days before expiration)
            if ($endsAt instanceof Carbon && $endsAt->isFuture() && $endsAt->diffInDays(now()) <= 3) {
                $daysLeft = $endsAt->diffInDays(now());
                $daysText = $daysLeft === 1 ? 'jour' : 'jours';
                return "Votre abonnement Pro expire dans {$daysLeft} {$daysText} - Renouvelez pour éviter le retour au plan gratuit";
            }

            // Check if it's expired but not yet processed by cron (status might still show as active)
            if ($endsAt instanceof Carbon && $endsAt->isPast()) {
                return "Votre abonnement Pro a expiré - Renouvelez pour éviter le retour au plan gratuit";
            }

            // Fallback for any other Pro non-active state
            return "Votre abonnement Pro n'est pas actif - Renouvelez pour retrouver les fonctionnalités Pro";
        }

        // Fallback for any other case
        return "Abonnement inconnu";
    }

    // HISTORIQUE & STATS

    public function historique6Mois(int $ownerId): array
    {
        $debut = Carbon::now()->subMonths(5)->startOfMonth();

        // ✅ 1 requête avec GROUP BY au lieu de 6 requêtes en boucle
        $revenus = Paiement::whereHas('bail.logement.propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('statut', 'payé')
            ->where('date_paiement', '>=', $debut)
            ->selectRaw('YEAR(date_paiement) as annee, MONTH(date_paiement) as mois, SUM(montant) as total')
            ->groupBy('annee', 'mois')
            ->get()
            ->mapWithKeys(function ($item) {
                return [sprintf('%04d-%02d', $item->annee, $item->mois) => (float) $item->total];
            });

        $historique = [];
        for ($i = 5; $i >= 0; $i--) {
            $mois = Carbon::now()->subMonths($i);
            $historique[] = [
                'mois'         => $mois->translatedFormat('M Y'),
                'mois_complet' => $mois->translatedFormat('F Y'),
                'revenus'      => $revenus[$mois->format('Y-m')] ?? 0,
            ];
        }

        return $historique;
    }

    public function statsParPropriete(int $ownerId)
    {
        return Propriete::withCount([
            'logements',
            'logements as logements_occupe_count'    => fn($q) => $q->where('statut_occupe', 'occupe'),
            'logements as logements_disponible_count' => fn($q) => $q->where('statut_occupe', 'disponible'),
        ])
            ->where('proprietaire_id', $ownerId)
            ->get()
            ->map(fn($p) => [
                'id'                  => $p->id,
                'nom'                 => $p->nom,
                'adresse'             => $p->adresse,
                'total_logements'     => $p->logements_count,
                'logements_occupe'    => $p->logements_occupe_count,
                'logements_disponible' => $p->logements_disponible_count,
                'taux_occupation'     => $p->logements_count > 0
                    ? round(($p->logements_occupe_count / $p->logements_count) * 100, 1)
                    : 0,
            ]);
    }
}
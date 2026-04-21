<?php

namespace App\Services\Proprietaire;

use App\Models\Bail;
use App\Models\Demande;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Propriete;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PropertyService
{
    // ═══════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════

    public function index()
    {
        return Propriete::all();
    }

    public function indexByOwner(int $ownerId)
    {
        return Propriete::where('proprietaire_id', $ownerId)->get();
    }

    public function creerPropriete(array $data, int $proprietaireId): Propriete
    {
        return Propriete::create(array_merge($data, [
            'proprietaire_id' => $proprietaireId,
        ]));
    }

    public function countByOwner(int $ownerId): int
    {
        return Propriete::where('proprietaire_id', $ownerId)->count();
    }

    public function search(array $filters, int $ownerId)
    {
        $query = Propriete::where('proprietaire_id', $ownerId);

        if (isset($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get();
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(int $ownerId): array
    {
        $debutMois = Carbon::now()->startOfMonth();
        $finMois   = Carbon::now()->endOfMonth();

        $totalProprietes = Propriete::where('proprietaire_id', $ownerId)->count();

        $statsLogements = Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->select(
                DB::raw('COUNT(*) as total_logements'),
                DB::raw('SUM(CASE WHEN statut_occupe = "occupe" THEN 1 ELSE 0 END) as total_occupe'),
                DB::raw('SUM(CASE WHEN statut_occupe = "disponible" THEN 1 ELSE 0 END) as total_disponible')
            )
            ->first();

        $demandesEnAttente = Demande::whereHas('logement.propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('status', 'en_attente') // ✅ 'status' cohérent avec le reste du code
            ->count();

        $bailsActifs = Bail::whereHas('logement.propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('statut', 'actif')
            ->count();

        $basePaiements = fn() => Paiement::whereHas('bail.logement.propriete', fn($q) => $q->where('proprietaire_id', $ownerId));

        $revenusMois = $basePaiements()
            ->where('statut', 'payé') // ✅ accent cohérent
            ->whereBetween('date_paiement', [$debutMois, $finMois])
            ->sum('montant');

        $paiementsAttendus = $basePaiements()
            ->whereIn('statut', ['impayé', 'en_retard'])
            ->whereBetween('date_echeance', [$debutMois, $finMois])
            ->sum('montant_restant');

        $tauxOccupation = ($statsLogements->total_logements ?? 0) > 0
            ? round(($statsLogements->total_occupe / $statsLogements->total_logements) * 100, 1)
            : 0;

        return [
            'stats_temps_reel' => [
                'total_proprietes'          => $totalProprietes,
                'total_logements'           => $statsLogements->total_logements ?? 0,
                'total_logements_occupe'    => $statsLogements->total_occupe ?? 0,
                'total_logements_disponible' => $statsLogements->total_disponible ?? 0,
                'taux_occupation'           => $tauxOccupation,
                'demandes_en_attente'       => $demandesEnAttente,
                'baux_actifs'               => $bailsActifs,
            ],
            'stats_mois_en_cours' => [
                'mois'               => Carbon::now()->translatedFormat('F Y'),
                'revenus_recus'      => $revenusMois,
                'paiements_attendus' => $paiementsAttendus,
                'revenus_potentiels' => $revenusMois + $paiementsAttendus,
            ],
        ];
    }

    // ═══════════════════════════════════════════
    // HISTORIQUE & STATS
    // ═══════════════════════════════════════════

    public function historique6Mois(int $ownerId): array
    {
        $debut = Carbon::now()->subMonths(5)->startOfMonth();

        // ✅ 1 requête avec GROUP BY au lieu de 6 requêtes en boucle
        $revenus = Paiement::whereHas('bail.logement.propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('statut', 'payé')
            ->where('date_paiement', '>=', $debut)
            ->select(
                DB::raw("DATE_FORMAT(date_paiement, '%Y-%m') as mois_key"),
                DB::raw('SUM(montant) as total')
            )
            ->groupBy('mois_key')
            ->pluck('total', 'mois_key');

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
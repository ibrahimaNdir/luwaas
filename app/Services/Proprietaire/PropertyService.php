<?php

namespace App\Services\Proprietaire;

use App\Models\Propriete;
use App\Models\Logement;
use App\Models\Demande;
use App\Models\Paiement;
use App\Models\Bail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class PropertyService
{
    public function index()
    {
        $proprietes = Propriete::all();
        return $proprietes ;
    }

    public function store(array $request)
    {
        $propriete= Propriete::create($request);
        return   $propriete;
    }

    public function show($id)
    {
        $propriete= Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        return  $propriete;
    }

    public function update(array $data, $id)
    {
        $propriete = Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        $propriete->update($data);
        return  $propriete;
    }

    public function destroy($id)
    {
        $offre = Propriete::find($id);

        if (!$offre) {
            return false;
        }

        $offre->delete();
        return true;
    }
    // Le nombre de propriete lie a un proprietaire
    public function countByOwner($Id)
    {
        return Propriete::where('proprietaire_id', $Id)->count();
    }

    // ✅ Rechercher / filtrer les propriétés
    public function search(array $filters, $ownerId)
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

    public function dashboard($ownerId)
    {
        // 📊 STATS TEMPS RÉEL (Propriétés & Logements)
        $totalProprietes = Propriete::where('proprietaire_id', $ownerId)->count();

        // ⚡ Optimisation : Comptage direct en base de données (pas de boucle)
        $statsLogements = Logement::whereHas('propriete', function($query) use ($ownerId) {
            $query->where('proprietaire_id', $ownerId);
        })
        ->select(
            DB::raw('COUNT(*) as total_logements'),
            DB::raw('SUM(CASE WHEN statut_occupe = "occupe" THEN 1 ELSE 0 END) as total_occupe'),
            DB::raw('SUM(CASE WHEN statut_occupe = "disponible" THEN 1 ELSE 0 END) as total_disponible')
        )
        ->first();

        // 📩 Demandes en attente
        $demandesEnAttente = Demande::whereHas('logement.propriete', function($query) use ($ownerId) {
            $query->where('proprietaire_id', $ownerId);
        })
        ->where('statut', 'en_attente')
        ->count();

        // 📅 STATS DU MOIS EN COURS
        $debutMois = Carbon::now()->startOfMonth();
        $finMois = Carbon::now()->endOfMonth();

        // 💰 Revenus du mois (paiements reçus)
        $revenusMoisEnCours = Paiement::whereHas('bail.logement.propriete', function($query) use ($ownerId) {
            $query->where('proprietaire_id', $ownerId);
        })
        ->where('statut', 'paye')
        ->whereBetween('date_paiement', [$debutMois, $finMois])
        ->sum('montant');

        // ⏳ Paiements attendus ce mois (impayés)
        $paiementsAttendusMois = Paiement::whereHas('bail.logement.propriete', function($query) use ($ownerId) {
            $query->where('proprietaire_id', $ownerId);
        })
        ->where('statut', 'en_attente')
        ->whereBetween('date_echeance', [$debutMois, $finMois])
        ->sum('montant');

        // 📈 Taux d'occupation
        $tauxOccupation = $statsLogements->total_logements > 0 
            ? round(($statsLogements->total_occupe / $statsLogements->total_logements) * 100, 1)
            : 0;

        // 🏆 Nombre de baux actifs
        $bailsActifs = Bail::whereHas('logement.propriete', function($query) use ($ownerId) {
            $query->where('proprietaire_id', $ownerId);
        })
        ->where('statut', 'actif')
        ->count();

        return [
            // 📊 Stats temps réel
            'stats_temps_reel' => [
                'total_proprietes' => $totalProprietes,
                'total_logements' => $statsLogements->total_logements ?? 0,
                'total_logements_occupe' => $statsLogements->total_occupe ?? 0,
                'total_logements_disponible' => $statsLogements->total_disponible ?? 0,
                'taux_occupation' => $tauxOccupation,
                'demandes_en_attente' => $demandesEnAttente,
                'baux_actifs' => $bailsActifs,
            ],

            // 📅 Stats du mois en cours
            'stats_mois_en_cours' => [
                'mois' => Carbon::now()->translatedFormat('F Y'), // "Janvier 2026"
                'revenus_recus' => $revenusMoisEnCours ?? 0,
                'paiements_attendus' => $paiementsAttendusMois ?? 0,
                'revenus_potentiels' => ($revenusMoisEnCours ?? 0) + ($paiementsAttendusMois ?? 0),
            ],
        ];
    }

    /**
     * Historique des revenus sur les 6 derniers mois
     */
    public function historique6Mois($ownerId)
    {
        $historique = [];

        for ($i = 5; $i >= 0; $i--) {
            $mois = Carbon::now()->subMonths($i);
            $debut = $mois->copy()->startOfMonth();
            $fin = $mois->copy()->endOfMonth();

            $revenus = Paiement::whereHas('bail.logement.propriete', function($query) use ($ownerId) {
                $query->where('proprietaire_id', $ownerId);
            })
            ->where('statut', 'paye')
            ->whereBetween('date_paiement', [$debut, $fin])
            ->sum('montant');

            $historique[] = [
                'mois' => $mois->translatedFormat('M Y'), // "Jan 2026"
                'mois_complet' => $mois->translatedFormat('F Y'), // "Janvier 2026"
                'revenus' => $revenus ?? 0,
            ];
        }

        return $historique;
    }

    /**
     * Statistiques détaillées par propriété
     */
    public function statsParPropriete($ownerId)
    {
        $proprietes = Propriete::withCount([
            'logements',
            'logements as logements_occupe_count' => function($query) {
                $query->where('statut_occupe', 'occupe');
            },
            'logements as logements_disponible_count' => function($query) {
                $query->where('statut_occupe', 'disponible');
            }
        ])
        ->where('proprietaire_id', $ownerId)
        ->get()
        ->map(function($propriete) {
            $tauxOccupation = $propriete->logements_count > 0
                ? round(($propriete->logements_occupe_count / $propriete->logements_count) * 100, 1)
                : 0;

            return [
                'id' => $propriete->id,
                'nom' => $propriete->nom,
                'adresse' => $propriete->adresse,
                'total_logements' => $propriete->logements_count,
                'logements_occupe' => $propriete->logements_occupe_count,
                'logements_disponible' => $propriete->logements_disponible_count,
                'taux_occupation' => $tauxOccupation,
            ];
        });

        return $proprietes;
    }





    // la Methode qui gere la propriete
    public function indexByOwner($ownerId)
    {
        return Propriete::where('proprietaire_id', $ownerId)->get();
    }



}

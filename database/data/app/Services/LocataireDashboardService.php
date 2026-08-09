<?php 

namespace App\Services;

use App\Models\Demande;
use App\Models\Bail;
use App\Models\Paiement;

class LocataireDashboardService
{
    public function dashboard(int $locataireId): array
    {
        $demandes = Demande::where('locataire_id', $locataireId)->count();
        
        $bauxActifs = Bail::whereHas('demande', fn($q) => $q->where('locataire_id', $locataireId))
            ->where('statut', 'actif')
            ->count();
        
        $paiementsEnAttente = Paiement::whereHas('bail.demande', fn($q) => $q->where('locataire_id', $locataireId))
            ->whereIn('statut', ['impayé', 'partiel', 'en_retard'])
            ->sum('montant_restant');

        return [
            'demandes_count' => $demandes,
            'baux_actifs_count' => $bauxActifs,
            'paiements_en_attente' => $paiementsEnAttente,
        ];
    }
}

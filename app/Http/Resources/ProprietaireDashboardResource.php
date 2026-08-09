<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProprietaireDashboardResource extends JsonResource
{
        public function toArray(Request $request): array
    {
        return [
            'stats_temps_reel' => [
                'total_proprietes' => $this['stats_temps_reel']['total_proprietes'] ?? 0,
                'total_logements' => $this['stats_temps_reel']['total_logements'] ?? 0,
                'total_logements_occupe' => $this['stats_temps_reel']['total_logements_occupe'] ?? 0,
                'total_logements_disponible' => $this['stats_temps_reel']['total_logements_disponible'] ?? 0,
                'taux_occupation' => $this['stats_temps_reel']['taux_occupation'] ?? 0,
                'demandes_en_attente' => $this['stats_temps_reel']['demandes_en_attente'] ?? 0,
                'baux_actifs' => $this['stats_temps_reel']['baux_actifs'] ?? 0,
            ],
            'stats_mois_en_cours' => [
                'mois' => $this['stats_mois_en_cours']['mois'] ?? null,
                'revenus_recus' => (int)($this['stats_mois_en_cours']['revenus_recus'] ?? 0),
                'paiements_attendus' => (int)($this['stats_mois_en_cours']['paiements_attendus'] ?? 0),
                'revenus_potentiels' => (int)($this['stats_mois_en_cours']['revenus_potentiels'] ?? 0),
                'paiements_en_retard' => (int)($this['stats_mois_en_cours']['paiements_en_retard'] ?? 0),
                'taux_recouvrement' => $this['stats_mois_en_cours']['taux_recouvrement'] ?? 0,
                'loyers_manuels_du_mois' => (int)($this['stats_mois_en_cours']['loyers_manuels_du_mois'] ?? 0),
                'afficher_alerte_retention' => (bool)($this['stats_mois_en_cours']['afficher_alerte_retention'] ?? false),
            ],
        ];
    }
}
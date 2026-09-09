<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Proprietaire;

class SubscriptionService
{
    protected SystemConfigService $configService;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Calcule le prix dynamique d'un abonnement selon le modèle économique.
     */
    public function calculatePrice(Proprietaire $proprietaire, Plan $plan): float
    {
        // 1. Prix de base
        $total = $plan->price_base_xof;

        // 2. Récupérer les logements publiés et disponibles
        $logements = $proprietaire->logements()
            ->where('statut_publication', 'publie')
            ->where('statut_occupe', 'disponible')
            ->get();

        // 3. Calculer le prix par bien avec les surcharges
        foreach ($logements as $logement) {
            $loyer = $logement->prix_loyer;
            $surcharge = $this->configService->getSurchargeForLoyer($loyer);
            
            $total += ($plan->price_per_property_xof + $surcharge);
        }

        return $total;
    }
}

<?php

namespace App\Services;

class CommissionService
{
    /**
     * Calcule la commission Luwaas sur un loyer.
     *
     * Le taux et le minimum sont définis dans config/luwaas.php
     * et surchargeable dans .env :
     *
     *   LUWAAS_COMMISSION_RATE=0.06   → 6% (défaut)
     *   LUWAAS_COMMISSION_MIN=6000    → 6 000 FCFA minimum (défaut)
     *
     * Pour changer le taux : modifier .env uniquement. Aucun code à toucher.
     *
     * @param  float $loyer  Montant brut du loyer (FCFA)
     * @return float         Commission Luwaas à prélever
     */
    public function calculerFraisLuwaas(float $loyer): float
    {
        $taux    = config('luwaas.commission_rate', 0.06);
        $minimum = config('luwaas.commission_min', 6000);

        return max((float) $minimum, $loyer * (float) $taux);
    }

    /**
     * Calcule le montant net que le bailleur reçoit après déduction des frais Luwaas.
     *
     * @param  float $loyer  Montant brut du loyer
     * @return float         Montant net reversé au bailleur
     */
    public function calculerMontantNetBailleur(float $loyer): float
    {
        return $loyer - $this->calculerFraisLuwaas($loyer);
    }

    /**
     * Calcule la marge brute Luwaas (commission - frais PSP).
     *
     * @param  float $loyer    Montant brut du loyer
     * @param  float $pspRate  Taux PSP de l'agrégateur (ex: 0.015 pour 1.5%)
     * @return float           Marge nette Luwaas
     */
    public function calculerMargeLuwaas(float $loyer, float $pspRate): float
    {
        $fraisLuwaas = $this->calculerFraisLuwaas($loyer);
        $fraisPsp    = $loyer * $pspRate;

        return $fraisLuwaas - $fraisPsp;
    }
}

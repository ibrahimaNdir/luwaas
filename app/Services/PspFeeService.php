<?php

namespace App\Services;

use App\Models\CommissionRate;
use Illuminate\Support\Facades\Log;

/**
 * Service pour résoudre les frais PSP de manière centralisée et historique.
 *
 * Ce service élimine la logique de résolution des frais PSP des gateways
 * et fournit une source unique de vérité pour les taux PSP applicables
 * à une transaction spécifique basée sur :
 * - gateway (paydunya, bictorys, etc.)
 * - operator (wave, orange_money, etc.)
 * - operation_type (payin, payout)
 * - date de la transaction
 *
 * Conformément aux exigences :
 * - Aucun fallback silencieux vers 1.5%
 * - Erreur explicite si taux manquant
 * - Taux historisés (valeur à la date de la transaction)
 * - Séparation totale entre commission Luwaas et frais PSP
 */
class PspFeeService
{
    /**
     * Récupère le taux PSP applicable pour une combinaison donnée.
     *
     * @param string $gateway     Identifier du gateway (paydunya, bictorys, etc.)
     * @param string $operator    Identifier de l'opérateur (wave, orange_money, etc.)
     * @param string $operationType Type d'opération (payin ou payout)
     * @param string $date        Date de l'opération (format Y-m-d ou objet DateTime)
     * @return array              Taux PSP contenant 'rate_percent' et 'fixed_fee'
     * @throws \Exception         Si aucun taux correspondant n'est trouvé
     */
    public function getPspRate(string $gateway, string $operator, string $operationType, $date): array
    {
        // Convertir la date en format approprié si nécessaire
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        }

        // Recherche du taux avec contraintes de validité temporelle
        $rate = CommissionRate::where('gateway', $gateway)
            ->where('operator', $operator)
            ->where('operation_type', $operationType)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from') // Prendre le taux le plus récent en cas de multiples correspondances
            ->first(['rate_percent', 'fixed_fee']);

        if (!$rate) {
            // Journaliser l'absence de configuration comme requis
            Log::error("Aucun taux PSP trouvé pour la combinaison: gateway={$gateway}, operator={$operator}, operation_type={$operationType}, date={$date}");

            // Lever une erreur explicite au lieu d'un fallback silencieux
            throw new \Exception("Configuration du taux PSP manquante pour: {$gateway} + {$operator} + {$operationType} + {$date}");
        }

        return [
            'rate_percent' => (float) $rate->rate_percent,
            'fixed_fee' => (float) $rate->fixed_fee
        ];
    }

    /**
     * Calcule les frais PSP attendus basée sur un montant et un taux.
     *
     * @param float $amount   Montant de la transaction
     * @param array $rateData  Taux PSP contenant 'rate_percent' et 'fixed_fee'
     * @return float          Frais PSP attendus
     */
    public function calculateExpectedPspFee(float $amount, array $rateData): float
    {
        $ratePercent = $rateData['rate_percent'];
        $fixedFee = $rateData['fixed_fee'];

        return ($amount * $ratePercent) + $fixedFee;
    }

    /**
     * Détermine le statut des frais PSP en fonction de la disponibilité du frais réel.
     *
     * @param float|null $actualFee Frais PSP réellement facturés (peut être null)
     * @return string               Statut: 'actual', 'expected', ou 'estimated'
     */
    public function determinePspFeeStatus(?float $actualFee): string
    {
        if ($actualFee !== null) {
            return 'actual';
        }

        // Pour l'instant, on considère que si on a pas de frais réel, c'est expected
        // Dans une implémentation plus complexe, on pourrait distinguer expected vs estimated
        // basé sur la disponibilité d'autres sources de données
        return 'expected';
    }

    /**
     * Calcule le montant net à verser au propriétaire après déduction des frais
     *
     * @param float $grossAmount     Montant brut collecté (loyers du propriétaire)
     * @param string $gateway        Gateway utilisé pour le paiement entrant (ex: paydunya)
     * @param string $payoutMethod   Canal de versement prévu (ex: bank_transfer, orange_money)
     * @param string $date           Date de référence pour les taux historiques
     * @return array                 Détail du calcul
     */
    public function calculateNetPayoutAmount(float $grossAmount, string $gateway, string $payoutMethod, $date): array
    {
        // 1. Frais de paiement entrant (ce que Luwaas a payé pour recevoir l'argent)
        // Utiliser 'unknown' comme opérateur lorsqu'il n'est pas spécifique à la transaction
        $payinFeeData = $this->getPspRate($gateway, 'unknown', 'payin', $date);
        $payinFee = $this->calculateExpectedPspFee($grossAmount, $payinFeeData);

        // 2. Commission Luwaas (à définir selon votre modèle économique)
        // Ceci devrait venir de la configuration des taux de commission
        $luwaasCommissionData = $this->getPspRate('luwaas', 'commission', 'commission', $date);
        $luwaasCommission = $this->calculateExpectedPspFee($grossAmount, $luwaasCommissionData);

        // 3. Frais de versement sortant (ce que coûtera l'envoi d'argent au propriétaire)
        $payoutFeeData = $this->getPspRate($gateway, $payoutMethod, 'payout', $date);
        $payoutFee = $this->calculateExpectedPspFee($grossAmount, $payoutFeeData);

        // Calcul du montant net
        $netAmount = $grossAmount - $payinFee - $luwaasCommission - $payoutFee;

        return [
            'gross_amount' => max(0, $grossAmount),
            'payin_fee' => max(0, $payinFee),
            'luwaas_commission' => max(0, $luwaasCommission),
            'payout_fee' => max(0, $payoutFee),
            'net_amount' => max(0, $netAmount), // Éviter les négatifs
            'breakdown' => [
                'payin_gateway' => $gateway,
                'payout_method' => $payoutMethod,
                'date_reference' => $date,
            ]
        ];
    }
}
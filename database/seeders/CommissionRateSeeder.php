<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommissionRate;

class CommissionRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Supprimer les enregistrements existants pour éviter les doublons
        CommissionRate::truncate();

        $rates = [];

        // =========================================
        // Taux PSP PayDunya
        // =========================================

        // PayDunya - Payin (réception de fonds)
        $operatorsPayin = ['wave', 'orange_money', 'free_money', 'card'];
        foreach ($operatorsPayin as $operator) {
            $rates[] = [
                'gateway' => 'paydunya',
                'operator' => $operator,
                'operation_type' => 'payin',
                'rate_percent' => $this->getPayDunyaPayinRate($operator),
                'fixed_fee' => 0,
                'valid_from' => '2020-01-01',
                'valid_to' => null,
            ];
        }

        // PayDunya - Payout (envoi de fonds)
        $operatorsPayout = ['wave', 'orange_money', 'free_money']; // Bictorys gère principalement les payouts
        foreach ($operatorsPayout as $operator) {
            $rates[] = [
                'gateway' => 'paydunya',
                'operator' => $operator,
                'operation_type' => 'payout',
                'rate_percent' => $this->getPayDunyaPayoutRate($operator),
                'fixed_fee' => 0,
                'valid_from' => '2020-01-01',
                'valid_to' => null,
            ];
        }

        // =========================================
        // Taux PSP Bictorys
        // =========================================

        // Bictorys - Payin (réception de fonds) - Peut ne pas être supporté
        foreach ($operatorsPayin as $operator) {
            $rates[] = [
                'gateway' => 'bictorys',
                'operator' => $operator,
                'operation_type' => 'payin',
                'rate_percent' => $this->getBictorysPayinRate($operator),
                'fixed_fee' => 0,
                'valid_from' => '2020-01-01',
                'valid_to' => null,
            ];
        }

        // Bictorys - Payout (envoi de fonds) - Principalement utilisé pour les payouts
        foreach ($operatorsPayout as $operator) {
            $rates[] = [
                'gateway' => 'bictorys',
                'operator' => $operator,
                'operation_type' => 'payout',
                'rate_percent' => $this->getBictorysPayoutRate($operator),
                'fixed_fee' => 0,
                'valid_from' => '2020-01-01',
                'valid_to' => null,
            ];
        }

        // =========================================
        // Taux de commission Luwaas
        // =========================================

        // Commission Luwaas : 6% avec minimum de 6000 FCFA
        // Modélisé comme un taux de 6% avec un fixed_fee qui assure le minimum
        // Pour un montant X, la commission sera max(X * 0.06, 6000)
        // On peut représenter cela comme : taux = 6% + fixed_fee qui diminue avec X
        // Mais pour simplifier dans ce modèle, on stocke le taux de base et on applique la logique du minimum ailleurs
        // Alternativement, on pourrait stocker un fixed_fee élevé et laisser le taux à 0%
        // Mais la exigence dit de le rendre configurable via commission_rates

        // Solution : Stocker un taux de 0% et un fixed_fee qui représente la commission minimale
        // Mais cela ne fonctionne pas pour la partie proportionnelle

        // Meilleure solution : Stocker le taux proportionnel (6%) et appliquer la logique du minimum dans le service
        // Le commission_rates contiendra alors juste le taux de base

        // Nouvelle approche : Utiliser un opérateur spécial 'commission' et type 'payout'
        $rates[] = [
            'gateway' => 'luwaas',
            'operator' => 'commission',
            'operation_type' => 'payout',
            'rate_percent' => 0.06, // 6%
            'fixed_fee' => 6000,    // Minimum de 6000 FCFA
            'valid_from' => '2020-01-01',
            'valid_to' => null,
        ];

        // Insérer tous les taux
        foreach ($rates as $rateData) {
            CommissionRate::create($rateData);
        }
    }

    /**
     * Retourne le taux de frais PayDunya pour une opération de payin
     */
    private function getPayDunyaPayinRate(string $operator): float
    {
        return match ($operator) {
            'wave', 'orange_money', 'free_money' => 0.015, // 1.5%
            'card' => 0.030, // 3%
            default => 0.015,
        };
    }

    /**
     * Retourne le taux de frais PayDunya pour une opération de payout
     */
    private function getPayDunyaPayoutRate(string $operator): float
    {
        // PayDunya peut ne pas supporter les payouts dans notre configuration
        // Mais on configure quand même les taux pour la complétude
        return match ($operator) {
            'wave', 'orange_money', 'free_money' => 0.015, // 1.5%
            default => 0.015,
        };
    }

    /**
     * Retourne le taux de frais Bictorys pour une opération de payin
     */
    private function getBictorysPayinRate(string $operator): float
    {
        // Bictorys peut ne pas supporter les payins dans notre configuration
        // Mais on configure quand même les taux pour la complétude
        return match ($operator) {
            'wave', 'orange_money', 'free_money' => 0.012, // 1.2% (exemple)
            'card' => 0.025, // 2.5% (exemple)
            default => 0.012,
        };
    }

    /**
     * Retourne le taux de frais Bictorys pour une opération de payout
     */
    private function getBictorysPayoutRate(string $operator): float
    {
        return match ($operator) {
            'wave', 'orange_money', 'free_money' => 0.012, // 1.2% (exemple)
            default => 0.012,
        };
    }
}

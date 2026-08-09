<?php

namespace Database\Seeders;

use App\Models\CommissionRate;
use Illuminate\Database\Seeder;

/**
 * Peuple les taux PSP par opérateur/agrégateur.
 *
 * Pour tester un nouvel agrégateur : ajouter une entrée ici avec ses taux réels
 * (récupérés dans la documentation sandbox de l'agrégateur).
 *
 * Exemple : Byctorys propose Wave à 1.2% → changer wave/byctorys à 0.0120
 *
 * La colonne "operator" suit la convention : {operateur}_{gateway}
 * → wave (PayDunya), orange_money (PayDunya), free_money (PayDunya), card (PayDunya)
 * → wave_byctorys, orange_money_byctorys, etc. quand tu testes Byctorys
 *
 * Les clés simples (wave, orange_money, ...) sont utilisées par le gateway actif par défaut.
 */
class CommissionRateSeeder extends Seeder
{
    public function run(): void
    {
        $today = today()->toDateString();

        $rates = [
            // ──────────────────────────────────────────
            // PayDunya (gateway par défaut)
            // Source : https://paydunya.com/tarifs (sandbox = même taux que prod)
            // ──────────────────────────────────────────
            [
                'operator'   => 'wave',
                'rate_percent' => 0.0150, // 1.5%
                'fixed_fee'  => 0,
                'valid_from' => $today,
                'valid_to'   => null,
            ],
            [
                'operator'   => 'orange_money',
                'rate_percent' => 0.0150, // 1.5%
                'fixed_fee'  => 0,
                'valid_from' => $today,
                'valid_to'   => null,
            ],
            [
                'operator'   => 'free_money',
                'rate_percent' => 0.0150, // 1.5%
                'fixed_fee'  => 0,
                'valid_from' => $today,
                'valid_to'   => null,
            ],
            [
                'operator'   => 'card',
                'rate_percent' => 0.0200, // 2%
                'fixed_fee'  => 0,
                'valid_from' => $today,
                'valid_to'   => null,
            ],

            // ──────────────────────────────────────────
            // Byctorys (à activer lors des tests sandbox)
            // Mettre à jour les taux réels après avoir consulté leur doc sandbox
            // ──────────────────────────────────────────
            // [
            //     'operator'   => 'wave_byctorys',
            //     'rate_percent' => 0.0120, // À vérifier avec Byctorys
            //     'fixed_fee'  => 0,
            //     'valid_from' => $today,
            //     'valid_to'   => null,
            // ],
            // [
            //     'operator'   => 'orange_money_byctorys',
            //     'rate_percent' => 0.0150, // À vérifier avec Byctorys
            //     'fixed_fee'  => 0,
            //     'valid_from' => $today,
            //     'valid_to'   => null,
            // ],

            // ──────────────────────────────────────────
            // CinetPay (à activer lors des tests sandbox)
            // ──────────────────────────────────────────
            // [
            //     'operator'   => 'wave_cinetpay',
            //     'rate_percent' => 0.0150, // À vérifier avec CinetPay
            //     'fixed_fee'  => 0,
            //     'valid_from' => $today,
            //     'valid_to'   => null,
            // ],
        ];

        foreach ($rates as $rate) {
            CommissionRate::updateOrCreate(
                ['operator' => $rate['operator'], 'valid_from' => $rate['valid_from']],
                $rate
            );
        }

        $this->command->info('✅ CommissionRates seedés : ' . count($rates) . ' taux.');
    }
}

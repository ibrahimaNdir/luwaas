<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // ─── STARTER MENSUEL ───────────────────────
            [
                'slug'                => 'starter-monthly',
                'name'                => 'Starter',
                'tier'                => 'starter',
                'billing_cycle'       => 'monthly',
                'price_xof'           => 5000,
                'biens_max'           => 5,
                'locataires_max'      => 5,
                'cogestionnaires_max' => 1,
                'features'            => json_encode([
                    'Gestion de 5 logements',
                    'Gestion de 5 locataires',
                    'Quittances PDF',
                    'Rappels email',
                    'Suivi des paiements',
                ]),
                'is_active'           => true,
            ],

            // ─── STARTER ANNUEL ────────────────────────
            [
                'slug'                => 'starter-yearly',
                'name'                => 'Starter',
                'tier'                => 'starter',
                'billing_cycle'       => 'yearly',
                'price_xof'           => 50000, // 2 mois offerts
                'biens_max'           => 5,
                'locataires_max'      => 5,
                'cogestionnaires_max' => 1,
                'features'            => json_encode([
                    'Gestion de 5 logements',
                    'Gestion de 5 locataires',
                    'Quittances PDF',
                    'Rappels email',
                    'Suivi des paiements',
                    '2 mois offerts',
                ]),
                'is_active'           => true,
            ],

            // ─── PRO MENSUEL ───────────────────────────
            [
                'slug'                => 'pro-monthly',
                'name'                => 'Pro',
                'tier'                => 'pro',
                'billing_cycle'       => 'monthly',
                'price_xof'           => 15000,
                'biens_max'           => 20,
                'locataires_max'      => null, // illimité
                'cogestionnaires_max' => 3,
                'features'            => json_encode([
                    'Gestion de 20 logements',
                    'Locataires illimités',
                    'Quittances PDF',
                    'Rappels email + SMS',
                    'Suivi des paiements',
                    'Export Excel',
                    'Statistiques avancées',
                    '3 co-gestionnaires',
                ]),
                'is_active'           => true,
            ],

            // ─── PRO ANNUEL ────────────────────────────
            [
                'slug'                => 'pro-yearly',
                'name'                => 'Pro',
                'tier'                => 'pro',
                'billing_cycle'       => 'yearly',
                'price_xof'           => 140000, // ~2 mois offerts
                'biens_max'           => 20,
                'locataires_max'      => null,
                'cogestionnaires_max' => 3,
                'features'            => json_encode([
                    'Gestion de 20 logements',
                    'Locataires illimités',
                    'Quittances PDF',
                    'Rappels email + SMS',
                    'Suivi des paiements',
                    'Export Excel',
                    'Statistiques avancées',
                    '3 co-gestionnaires',
                    '2 mois offerts',
                ]),
                'is_active'           => true,
            ],

            // ─── ENTERPRISE ────────────────────────────
            [
                'slug'                => 'enterprise',
                'name'                => 'Enterprise',
                'tier'                => 'enterprise',
                'billing_cycle'       => 'monthly',
                'price_xof'           => 0, // sur devis
                'biens_max'           => null, // illimité
                'locataires_max'      => null,
                'cogestionnaires_max' => null,
                'features'            => json_encode([
                    'Logements illimités',
                    'Locataires illimités',
                    'Co-gestionnaires illimités',
                    'Toutes les fonctionnalités Pro',
                    'Support prioritaire',
                    'Onboarding personnalisé',
                    'Facturation sur devis',
                ]),
                'is_active'           => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']], // clé unique
                $plan
            );
        }

        $this->command->info('✅ Plans créés avec succès !');
    }
}
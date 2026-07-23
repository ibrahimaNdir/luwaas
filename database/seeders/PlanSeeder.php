<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'             => 'free',
                'name'             => 'Gratuit',
                'tier'             => 'free',
                'billing_cycle'    => null,
                'price_xof'        => 0,
                'publications_max' => 1,
                'features' => [
                    '1 annonce active pendant 15 jours',
                    'Gestion de vos propriétés',
                    'Gestion de vos logements',
                    'Gestion des locataires',
                    'Création de baux',
                    'Quittances PDF',
                ],
                'is_active'        => true,
            ],
            [
                'slug'             => 'pro-monthly',
                'name'             => 'Pro',
                'tier'             => 'pro',
                'billing_cycle'    => 'monthly',
                'price_xof'        => 5000,
                'publications_max' => 10,
                'features' => [
                    '10 annonces actives pendant 30 jours',
                    'Tout le plan Gratuit',
                    'Mise en avant des logements',
                    'Rapports financiers avancés',
                    'Export Excel',
                    'Rappels automatiques de loyer par SMS',
                    'Historique complet des paiements',
                    'Support prioritaire',
                ],
                'is_active'        => true,
            ],
            [
                'slug'             => 'pro-yearly',
                'name'             => 'Pro Annuel',
                'tier'             => 'pro',
                'billing_cycle'    => 'yearly',
                'price_xof'        => 48000,
                'publications_max' => 10,
                'features' => [
                    'Tout le plan Pro',
                    'Économisez 12 000 FCFA par an',
                    '2 mois offerts',
                    'Accès prioritaire aux nouvelles fonctionnalités',
                ],
                'is_active'        => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}

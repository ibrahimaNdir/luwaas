<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PLAN STARTER (5 logements max - GRATUIT)
        Plan::create([
            'slug'             => 'starter',
            'name'             => 'Starter',
            'tier'             => 'starter',
            'billing_cycle'    => 'monthly',
            'price_xof'        => 0, // Gratuit
            'publications_max' => 5,
            'is_active'        => true,
            'features'         => [
                'Gestion des propriétés',
                'Gestion des locataires',
                'Gestion des baux',
                'Paiement des loyers',
                'Tableau de bord basique'
            ],
        ]);

        // PLAN PRO (15 logements max + fonctionnalités avancées - PAYANT)
        Plan::create([
            'slug'             => 'pro-monthly',
            'name'             => 'Pro',
            'tier'             => 'pro',
            'billing_cycle'    => 'monthly',
            'price_xof'        => 10000, // 10,000 FCFA par mois (à ajuster selon vos besoins)
            'publications_max' => 15,
            'is_active'        => true,
            'features'         => [
                'Gestion des propriétés',
                'Gestion des locataires',
                'Gestion des baux',
                'Paiement des loyers',
                'Tableau de bord basique',
                'Mise en avant des logements',
                'Rapports financiers avancés',
                'Export Excel'
            ],
        ]);
    }
}
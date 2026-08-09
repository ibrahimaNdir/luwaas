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
                'slug'                   => 'starter',
                'name'                   => 'Starter',
                'tier'                   => 'starter',
                'billing_cycle'          => null, // Pas de cycle de facturation
                'price_base_xof'         => 0,
                'price_per_property_xof' => 0,
                'price_xof'              => 0, // Gratuit
                'publications_max'       => null, // Illimité
                'features'               => json_encode([
                    'Gestion de base',
                    'Quittances automatiques',
                    'Dashboard basique',
                    'Publication illimitée',
                ]),
                'is_active'              => true,
            ],
            [
                'slug'                   => 'pro-monthly',
                'name'                   => 'Pro (Mensuel)',
                'tier'                   => 'pro',
                'billing_cycle'          => 'monthly',
                'price_base_xof'         => 5000,
                'price_per_property_xof' => 0, // Plus de frais par propriété
                'price_xof'              => 5000,
                'publications_max'       => null, // Illimité
                'features'               => json_encode([
                    'Tout le plan Starter',
                    'Rapports financiers avancés',
                    'Export Excel',
                    'Rappels automatiques de loyer',
                    'Historique complet',
                    'Support prioritaire',
                    'Mise en avant des logements',
                ]),
                'is_active'              => true,
            ],
            [
                'slug'                   => 'pro-yearly',
                'name'                   => 'Pro (Annuel)',
                'tier'                   => 'pro',
                'billing_cycle'          => 'yearly',
                'price_base_xof'         => 48000,
                'price_per_property_xof' => 0,
                'price_xof'              => 48000,
                'publications_max'       => null,
                'features'               => json_encode([
                    'Tout le plan Pro',
                    'Économisez 12 000 FCFA/an',
                ]),
                'is_active'              => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        // Nettoyage de l'ancien plan free_trial s'il existe
        Plan::where('slug', 'free_trial')->delete();
    }
}

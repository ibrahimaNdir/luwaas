<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Proprietaire;

class ProprietoreDataMigration extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Migrer les propriétaires avec l'ancien plan "gratuit" (1 logement) vers Starter (5 logements)
        $freePlanProprietaires = Proprietaire::where('plan', 'free')->get();

        foreach ($freePlanProprietaires as $proprietaire) {
            $proprietaire->update([
                'plan' => 'starter',
                // Garder le statut d'abonnement tel qu'il était (souvent active ou free_trial)
                // Mais si c'était free_trial, on le convertit en active puisque Starter est actif par défaut
                'subscription_status' => $proprietaire->subscription_status === 'free_trial' ? 'active' : $proprietaire->subscription_status,
                // S'assurer qu'il n'y a pas de date d'essai pour Starter
                'trial_ends_at' => null,
                // Starter n'a pas de date d'expiration périodique
                'subscription_ends_at' => null,
                'billing_cycle' => null,
            ]);
        }

        $this->command->info("Migrés {$freePlanProprietaires->count()} propriétaires du plan gratuit vers Starter");

        // 2. Convertir les propriétaires en essai gratuit en Starter actif
        $trialProprietaires = Proprietaire::where('subscription_status', 'free_trial')
            ->where('plan', '!=', 'starter') // Éviter de double-count ceux déjà migratés
            ->get();

        foreach ($trialProprietaires as $proprietaire) {
            $proprietaire->update([
                'plan' => 'starter',
                'subscription_status' => 'active', // Starter est actif par défaut
                'trial_ends_at' => null,           // Pas d'essai pour Starter
                'subscription_ends_at' => null,    // Starter n'expire pas
                'billing_cycle' => null,           // Pas de facturation pour Starter
            ]);
        }

        $this->command->info("Convertis {$trialProprietaires->count()} propriétaires en essai gratuit vers Starter actif");

        // 3. S'assurer que tous les propriétaires ont un plan valide (fallback vers Starter)
        $noPlanProprietaires = Proprietaire::whereNull('plan')
            ->orWhere('plan', '')
            ->get();

        foreach ($noPlanProprietaires as $proprietaire) {
            $proprietaire->update([
                'plan' => 'starter',
                'subscription_status' => 'active',
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
                'billing_cycle' => null,
            ]);
        }

        $this->command->info("Assignés {$noPlanProprietaires->count()} propriétaires sans plan vers Starter");

        // 4. Nettoyer les éventuelles incohérences (optionnel mais recommandé)
        // S'assurer que si le plan est starter, le statut est actif
        $inconsistentStarter = Proprietaire::where('plan', 'starter')
            ->where('subscription_status', '!=', 'active')
            ->get();

        foreach ($inconsistentStarter as $proprietaire) {
            $proprietaire->update([
                'subscription_status' => 'active',
            ]);
        }

        if ($inconsistentStarter->isNotEmpty()) {
            $this->command->info("Corrigé {$inconsistentStarter->count()} propriétaires Starter avec statut incohérent");
        }

        $this->command->info('Migration des données propriétaire terminée avec succès');
    }
}
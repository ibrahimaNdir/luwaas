<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PayoutMethod;
use App\Models\Proprietaire;

class PayoutMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer une méthode de versement par défaut pour les propriétaires existants
        $proprietaires = Proprietaire::all();

        foreach ($proprietaires as $proprietaire) {
            // Vérifier si le propriétaire n'a pas déjà une méthode de versement
            if ($proprietaire->payoutMethods()->count() === 0) {
                // Créer une méthode de versement par défaut (Orange Money)
                PayoutMethod::create([
                    'proprietaire_id' => $proprietaire->id,
                    'payout_channel' => 'orange_money',
                    'payout_phone' => '+221' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'is_default' => true,
                    'verified_at' => now(),
                ]);

                // Créer une deuxième méthode de versement (virement bancaire) en option
                if (mt_rand(1, 100) <= 30) { // 30% de chance d'avoir une deuxième méthode
                    PayoutMethod::create([
                        'proprietaire_id' => $proprietaire->id,
                        'payout_channel' => 'bank_transfer',
                        'payout_phone' => null, // Pas de téléphone pour les virements bancaires
                        'is_active' => true,
                        'is_default' => false,
                        'verified_at' => now(),
                    ]);
                }
            }
        }

        // Si aucun propriétaire n'existe, en créer un exemple avec des méthodes de versement
        if ($proprietaires->isEmpty()) {
            // Cette partie ne devrait normalement jamais s'exécuter car il y a déjà des propriétaires
            // via les autres seeders, mais c'est une sécurité
            $proprietaire = Proprietaire::factory()->create();

            PayoutMethod::create([
                'proprietaire_id' => $proprietaire->id,
                'payout_channel' => 'orange_money',
                'payout_phone' => '+221' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'is_active' => true,
                'is_default' => true,
                'verified_at' => now(),
            ]);
        }
    }
}
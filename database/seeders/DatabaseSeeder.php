<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            RegionSeeder::class,
            DepartementSeeder::class,
            CommuneSeeder::class,
            AdminSeeder::class,
            PlanSeeder::class,
            CommissionRateSeeder::class, // Taux PSP par opérateur (modifiable sans code)
            // ProprietoreDataMigration::class, // Migration des données propriétaire après mise à jour des plans (temporairement désactivé)
            CommissionRateSeeder::class,
            PlatformSettingsTableSeeder::class,
            PayoutMethodSeeder::class,
        ]);
    }
}
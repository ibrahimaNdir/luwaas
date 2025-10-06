<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Création du user de base
        $user = User::create([
            'prenom' => 'Super',
            'nom' => 'Admin',
            'telephone' => '771234567',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);

        // Création de l’admin lié
        Admin::create([
            'user_id' => $user->id,
            'admin_id' => 'ADM001',
            'username' => 'superadmin',
        ]);

        //
    }
}

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

        $user = User::firstOrCreate(
            ['email' => 'admins@example.com'],
            [
                'prenom' => 'Super',
                'nom' => 'Admins',
                'telephone' => '771234569',
                'password' => Hash::make('passwords123'),
                'user_type' => 'admin',
                'is_active' => true,
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $user->id],
            [
                'admin_id' => 'ADM002',
                'username' => 'superadmins',
            ]
        );

        //
    }
}

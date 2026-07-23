<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL');
        $password = env('ADMIN_SEED_PASSWORD');
        $telephone = env('ADMIN_SEED_PHONE');

        if (!$email || !$password || !$telephone) {
            $this->command->error('ADMIN_SEED_EMAIL, ADMIN_SEED_PASSWORD et ADMIN_SEED_PHONE doivent être définis dans .env');
            return;
        }

        $user = User::where('email', $email)
            ->orWhere('telephone', $telephone)
            ->first();

        if ($user) {
            $user->update([
                'prenom' => 'Super',
                'nom' => 'Admin',
                'email' => $email,
                'telephone' => $telephone,
                'password' => Hash::make($password),
                'user_type' => 'admin',
                'is_active' => true,
                'phone_verified_at' => Carbon::now(),
                'phone_otp' => null,
                'phone_otp_expires_at' => null,
                'otp_attempts' => 0,
            ]);
        } else {
            $user = User::create([
                'prenom' => 'Super',
                'nom' => 'Admin',
                'email' => $email,
                'telephone' => $telephone,
                'password' => Hash::make($password),
                'user_type' => 'admin',
                'is_active' => true,
                'phone_verified_at' => Carbon::now(),
                'phone_otp' => null,
                'phone_otp_expires_at' => null,
                'otp_attempts' => 0,
            ]);
        }

        Admin::updateOrCreate(
            ['user_id' => $user->id],
            [
                'admin_id' => 'ADM002',
                'username' => 'superadmin',
            ]
        );

        $this->command->warn("Admin prêt avec l'email {$email}");
    }
}
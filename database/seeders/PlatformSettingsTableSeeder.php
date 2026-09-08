<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformSetting;

class PlatformSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing settings (optional, for fresh seed)
        PlatformSetting::truncate();

        // Insert initial active payment gateway
        PlatformSetting::create([
            'key' => 'active_payment_gateway',
            'value' => 'paydunya',
            'description' => 'Gateway PSP actif utilisé pour le pay-in et le payout',
        ]);
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value');
                $table->text('description')->nullable();
                $table->timestamps();
            });

            // Insert initial data
            DB::table('platform_settings')->insert([
                'key' => 'active_payment_gateway',
                'value' => 'paydunya',
                'description' => 'Gateway PSP actif utilisé pour le pay-in et le payout',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
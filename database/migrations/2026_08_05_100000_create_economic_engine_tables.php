<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mise à jour des propriétaires (pour le déboursement)
        Schema::table('proprietaires', function (Blueprint $table) {
            $table->string('payout_channel')->nullable();
            $table->string('payout_phone')->nullable();
            $table->decimal('solde_credit', 15, 2)->default(0);
        });


        // 3. Table des configurations système
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('proprietaires', function (Blueprint $table) {
            $table->dropColumn(['payout_channel', 'payout_phone', 'solde_credit']);
        });


        Schema::dropIfExists('system_settings');
    }
};

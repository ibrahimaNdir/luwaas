<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('verification_token', 64)
                ->nullable()
                ->unique()
                ->after('periode')
                ->comment('Token unique pour la page de vérification QR de la quittance');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('verification_token');
        });
    }
};

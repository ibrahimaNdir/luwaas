<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locataires', function (Blueprint $table) {
            // ── Score global de fiabilité (0–100)
            $table->unsignedTinyInteger('score_fiabilite')->default(100)->after('is_actif');

            // ── Compteurs bruts (uniquement paiements EN LIGNE)
            $table->unsignedInteger('total_paiements_en_ligne')->default(0)->after('score_fiabilite');
            $table->unsignedInteger('total_paiements_a_temps')->default(0)->after('total_paiements_en_ligne');
            $table->unsignedInteger('total_paiements_en_retard')->default(0)->after('total_paiements_a_temps');
        });
    }

    public function down(): void
    {
        Schema::table('locataires', function (Blueprint $table) {
            $table->dropColumn([
                'score_fiabilite',
                'total_paiements_en_ligne',
                'total_paiements_a_temps',
                'total_paiements_en_retard',
            ]);
        });
    }
};


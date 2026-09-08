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
        Schema::table('demandes', function (Blueprint $table) {
            $table->unique(['logement_id', 'locataire_id'], 'unique_demande_active')
                ->where(function ($query) {
                    $query->whereIn('status', ['en_attente', 'acceptee']);
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropUnique('unique_demande_active');
        });
    }
};

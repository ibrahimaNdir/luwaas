<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();

            // ═══════════════════════════════════════════════════════════
            // RELATIONS
            // ═══════════════════════════════════════════════════════════
            $table->foreignId('locataire_id')
                ->constrained('locataires')
                ->onDelete('cascade');

            $table->foreignId('bail_id')
                ->constrained('baux')
                ->onDelete('cascade');

            // ═══════════════════════════════════════════════════════════
            // TYPE DE PAIEMENT
            // ═══════════════════════════════════════════════════════════
            $table->enum('type', [
                'signature',              // Paiement initial (caution + 1er mois)
                'loyer_mensuel',          // Loyers mensuels
                'caution_complementaire', // Complément caution
            ])->default('loyer_mensuel');

            // ═══════════════════════════════════════════════════════════
            // MONTANTS
            // ═══════════════════════════════════════════════════════════
            $table->decimal('montant_attendu', 10, 2);
            $table->decimal('montant_paye', 10, 2)->default(0);
            $table->decimal('montant_restant', 10, 2)->default(0);

            // ═══════════════════════════════════════════════════════════
            // STATUT
            // ═══════════════════════════════════════════════════════════
            $table->enum('statut', [
                'impayé',
                'payé',
                'partiel',
                'en_retard',
            ])->default('impayé');

            // ═══════════════════════════════════════════════════════════
            // DATES
            // ═══════════════════════════════════════════════════════════
            $table->date('date_echeance');
            $table->date('date_paiement')->nullable();
            $table->string('periode')->nullable(); // "Février 2025"

            // ═══════════════════════════════════════════════════════════
            // TIMESTAMPS & INDEX
            // ═══════════════════════════════════════════════════════════
            $table->timestamps();

            $table->index('bail_id');
            $table->index('locataire_id');
            $table->index('statut');
            $table->index('type');
            $table->index('date_echeance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
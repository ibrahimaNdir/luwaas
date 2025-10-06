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
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('locataire_id')
                ->constrained('locataires')
                ->onDelete('cascade');

            $table->foreignId('bail_id')
                ->constrained('baux')
                ->onDelete('cascade');

            // Montant attendu
            $table->decimal('montant_attendu', 10, 2);

            // Statut métier
            $table->enum('statut', ['en_attente', 'valide', 'partiel', 'en_retard'])
                ->default('en_attente');

            // Échéance
            $table->integer('mois');   // ex: 10
            $table->integer('annee');  // ex: 2025

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};


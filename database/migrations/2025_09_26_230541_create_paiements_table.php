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
            $table->decimal('montant_paye', 10, 2)->default(0); // montant payé
            $table->decimal('montant_restant', 10, 2)->default(0); // reste à payer




            $table->enum('statut', ['payé', 'partiel', 'en_retard', 'impayé'])
                ->default('en_retard');


            // Échéance
            $table->date('date_echeance');   // ex: 2025-02-05 (échéance de février)
            $table->date('date_paiement')->nullable(); // ex: 2025-04-10 (payé en avril)
            $table->string('periode')->nullable(); // ex: "Février 2025"

            $table->index('bail_id');
            $table->index('locataire_id');
            $table->index('statut');

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


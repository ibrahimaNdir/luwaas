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
        Schema::create('baux', function (Blueprint $table) {
            // Clé primaire
            $table->id();

            // Attributs métier
            $table->decimal('montant_loyer', 10, 2);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('garantie', 10, 2)->default(0);

            $table->enum('statut', [
                'actif',
                'expire',
                'resilie',
                'en_attente',
                'suspendu'
            ])->default('en_attente');
            // Relations
            $table->foreignId('logement_id')
                ->constrained('logements')
                ->onDelete('cascade');

            $table->foreignId('locataire_id')
                ->constrained('locataires')
                ->onDelete('cascade');

            // Attributs additionnels
            $table->text('conditions_particulieres')->nullable();
            $table->json('clauses_additionnelles')->nullable();
            $table->date('date_signature')->nullable();
            $table->decimal('charges_mensuelles', 8, 2)->default(0);
            $table->boolean('charges_incluses')->default(false);

            $table->integer('jour_echeance')->default(1);
            $table->boolean('renouvellement_automatique')->default(false);

            // Timestamps
            $table->timestamps();

            // Index utiles
            $table->index(['statut', 'date_fin']);
            $table->index(['date_debut', 'date_fin']);







        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baux');
    }
};

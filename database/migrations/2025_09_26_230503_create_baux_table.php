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
            $table->id();

            $table->foreignId('logement_id')
                ->constrained('logements')
                ->onDelete('cascade');

            $table->foreignId('locataire_id')
                ->constrained('locataires')
                ->onDelete('cascade');

            $table->integer('charges_mensuelles')->default(0);
            $table->integer('caution')->default(0);
            $table->integer('montant_loyer')->default(0);
            $table->integer('cautions_a_payer')->default(0);



            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', [
                'actif',
                'expire',
                'resilie',
                'en_attente',
                'suspendu'
            ])->default('actif');
            $table->integer('jour_echeance')->default(1);
            $table->boolean('renouvellement_automatique')->default(false);

            $table->timestamps();

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

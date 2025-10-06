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
        Schema::create('transactions', function (Blueprint $table) {
            // Clé primaire

            $table->id();

            // Relation avec le paiement attendu
            $table->foreignId('paiement_id')
                ->constrained('paiements')
                ->onDelete('cascade');

            // Montant payé (peut être partiel)
            $table->decimal('montant', 10, 2);

            // Statut technique de la transaction
            $table->enum('statut', ['pending', 'success', 'failed', 'refunded'])
                ->default('pending');

            // Informations sur le provider
            $table->enum('provider', [
                'wave',
                'orange_money',
                'paypal',
                'carte_bancaire',
                'espece'
            ])->nullable(); // null si pas encore défini

            // Référence externe (ID du provider)
            $table->string('transaction_ref')->nullable();

            // Frais éventuels
            $table->decimal('frais', 10, 2)->default(0);

            // Réponse brute de l’API ou justificatif
            $table->json('raw_response')->nullable();

            // Date réelle de la transaction
            $table->timestamp('date_transaction')->nullable();

            $table->timestamps();

            // Index utiles
            $table->index('statut');
            $table->index('provider');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

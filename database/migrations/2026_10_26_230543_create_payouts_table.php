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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained();
            $table->foreignId('transaction_id')->constrained(); // Lien vers le paiement initial
            $table->decimal('montant', 15, 2);
            $table->string('reference_paydunya')->nullable();
            $table->string('statut')->default('pending'); // pending, success, failed
            $table->text('erreur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

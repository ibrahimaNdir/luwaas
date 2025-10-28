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
        Schema::create('locataires', function (Blueprint $table) {
            $table->id();

            // Lien avec la table users
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('locataire_id')->unique(); // Identifiant unique propre au locataire
            $table->string('cni')->unique();
            $table->boolean('is_actif')->default(true); // Activation/désactivation sans suppression


            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locataires');
    }
};

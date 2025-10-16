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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('prenom');
            $table->string('nom');
            $table->string('telephone')->unique();
            $table->string('email')->unique();
            $table->string('password'); // mot de passe hashé en backend
            $table->boolean('is_active')->default(true);
            $table->enum('user_type', ['proprietaire', 'locataire', 'admin']);
            $table->json('profile')->nullable(); // infos supplémentaires flexibles (adresse, photo, etc.)
            $table->rememberToken(); // pour l’auth Laravel (sessions/cookies)
            $table->timestamps();

            $table->index('user_type'); // optimisation des requêtes par rôle
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

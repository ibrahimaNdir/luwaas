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
        Schema::create('proprietaires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade'); // suppression en cascade si utilisateur supprimé

            $table->string('proprietaire_id')->unique(); // identifiant propre au propriétaire (ex: ID interne)
            $table->string('cni')->unique();              // Carte Nationale d’Identité, unique obligatoire
            $table->boolean('is_actif')->default(true);  // pour désactivation sans suppression
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proprietaires');
    }
};

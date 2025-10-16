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
        Schema::create('logements', function (Blueprint $table) {
            $table->id();
            // Informations liées au logement
            $table->string('numero');
            $table->float('superficie')->nullable();
            $table->integer('nombre_pieces')->nullable();
            $table->boolean('meuble')->default(false);
            $table->enum('etat', ['excellent', 'bon', 'moyen', 'renovation_requise'])->default('bon');
            $table->enum('typelogement', ['studio', 'appartement', 'maison', 'villa'])->default('maison');
            $table->text('description')->nullable();
            $table->decimal('prix_indicatif', 10, 2)->nullable()->default(null);

            // Statuts
            $table->enum('statut_occupe', ['disponible', 'occupe', 'reserve'])->default('disponible');
            $table->enum('statut_publication', ['brouillon', 'publie'])->default('brouillon');

            $table->foreignId('propriete_id')->constrained('proprietes')->onDelete('cascade');

            $table->unique(['propriete_id', 'numero']);
            $table->index('statut_occupe');
            $table->index('statut_publication');

            $table->timestamps();
        });

    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logements');
    }
};

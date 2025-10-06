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
        Schema::create('proprietes', function (Blueprint $table) {
            $table->id();


            // Relation avec le propriétaire
            $table->foreignId('proprietaire_id')
                ->constrained('proprietaires')
                ->onDelete('cascade');

            // Localisation (clés étrangères)
            $table->foreignId('region_id')
                ->constrained('regions')
                ->onDelete('restrict');

            $table->foreignId('departement_id')
                ->constrained('departements')
                ->onDelete('restrict');

            $table->foreignId('commune_id')
                ->constrained('communes')
                ->onDelete('restrict');

            // Infos générales sur la propriété
            $table->string('titre'); // ex: "Immeuble Médina"
            $table->string('type');  // maison, appartement, immeuble, terrain, etc.
            $table->text('description')->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proprietes');
    }
};

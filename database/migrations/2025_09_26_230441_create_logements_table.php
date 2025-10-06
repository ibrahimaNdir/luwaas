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
            // Clé primaire
            $table->id(); // auto-incrémentée


            // Attributs métier
            $table->string('numero'); // ex: Appartement A1, Studio B2
            $table->float('superficie')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_occupe')->default(false);

            // Relation avec la propriété
            $table->foreignId('propriete_id')
                ->constrained('proprietes')
                ->onDelete('cascade');

            // Attributs additionnels
            $table->integer('nombre_pieces')->nullable();
            $table->boolean('meuble')->default(false);
            $table->enum('etat', ['excellent', 'bon', 'moyen', 'renovation_requise'])
                ->default('bon');

            // Contraintes et index
            $table->unique(['propriete_id', 'numero']); // Numéro unique par propriété
            $table->index('is_occupe');


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

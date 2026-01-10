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
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')
                ->constrained('logements')
                ->onDelete('cascade');

            $table->foreignId('locataire_id')
                ->constrained('locataires')
                ->onDelete('cascade');

            $table->foreignId('proprietaire_id')
                ->constrained('proprietaires')
                ->onDelete('cascade');

            $table->timestamp('date_demande')->useCurrent();

             // LE CHAMP MAGIQUE 👇
        $table->string('status')->default('en_attente'); 
       


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};

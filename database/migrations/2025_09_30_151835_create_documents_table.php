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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propriete_id')
                ->constrained('proprietes')
                ->onDelete('cascade');
            $table->enum('type', [
                'piece_identite',
                'titre_propriete',
                'justificatif_domicile',
            ]);
            $table->string('fichier_url'); // lien vers le fichier (storage ou CDN)
            $table->enum('statut', ['en_attente', 'valide', 'refuse'])->default('en_attente');
            $table->text('commentaire_admin')->nullable(); // remarque en cas de refus

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logements', function (Blueprint $table) {
            $table->id();

            // --- IDENTIFICATION ---
            $table->foreignId('propriete_id')->constrained('proprietes')->onDelete('cascade');
            
            // "numero" : Sert d'identifiant unique dans la propriété (Ex: "A2", "Villa", "Chambre 1")
            $table->string('numero'); 
            
            $table->enum('typelogement', ['studio', 'appartement', 'maison', 'villa']);

            // --- DÉTAILS PHYSIQUES (MODIFIÉS) ---
            $table->float('superficie')->nullable();
            
            // REMPLACEMENT : On supprime 'nombre_pieces' pour être plus précis
            $table->tinyInteger('nombre_chambres');       // Ex: 2 (Sert à calculer F3)
            $table->tinyInteger('nombre_salles_de_bain'); // Ex: 1 ou 2 (Facteur de prix)
            
            

            $table->boolean('meuble')->default(false);
            $table->enum('etat', ['neuf', 'excellent', 'moyen', 'renovation_requise'])->default('bon');
            $table->text('description')->nullable(); // Pour balcon, chauffe-eau, etc.

            // --- PARTIE FINANCIÈRE (SÉNÉGAL COMPLIANT) ---
            $table->integer('prix_loyer'); // Ex: 150000

            

        

            // --- STATUTS ---
            $table->enum('statut_occupe', ['disponible', 'occupe', 'reserve'])->default('disponible');
            $table->enum('statut_publication', ['brouillon', 'publie'])->default('brouillon');

            $table->timestamps();

            // --- CONTRAINTES ---
            // Un seul "A2" par immeuble. Un seul "Villa" par propriété.
            $table->unique(['propriete_id', 'numero']);
            
            // Index pour la recherche rapide
            $table->index('statut_occupe');
            $table->index('statut_publication');
            $table->index('prix_loyer'); // Utile pour filtrer par budget
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logements');
    }
};

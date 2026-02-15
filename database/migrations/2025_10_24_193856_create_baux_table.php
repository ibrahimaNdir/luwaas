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
        Schema::create('baux', function (Blueprint $table) {
            $table->id();

            // RELATIONS
            $table->foreignId('demande_id')->nullable()->constrained('demandes')->onDelete('set null');
            $table->foreignId('logement_id')->constrained('logements')->onDelete('cascade');
            $table->foreignId('locataire_id')->constrained('locataires')->onDelete('cascade');

            // FINANCES (Ici j'ai corrigé les noms)
            $table->integer('montant_loyer'); // Pas de default(0), un bail a forcement un prix !
            $table->integer('charges_mensuelles')->default(0);
            $table->integer('nombre_mois_caution'); // Loyer + Charges

            // Gestion de la Caution (Clarté absolue)
            $table->integer('montant_caution_total'); // La dette totale (ex: 200.000)
            $table->integer('montant_caution_paye')->default(0); // Ce qu'il a déjà versé (ex: 100.000)
            // Note: Le "reste à payer" se calcule tout seul (Total - Payé), pas besoin de le stocker.

            // DATES & STATUTS
            $table->date('date_debut');
            $table->date('date_fin');

            $table->enum('statut', [
                'brouillon',    // Etape 1 : Saisie + Impression
                'en_attente',   // Etape 2 : En attente signature
                'actif',        // Etape 3 : Validé (Locataire dedans)
                'expire',
                'resilie',
                'suspendu'     
            ])->default('brouillon'); // ← CHANGEMENT CRUCIAL ICI

            // Dans la table 'baux'
            $table->string('document_scan_path')->nullable();


            $table->integer('jour_echeance')->default(5); // Le 5 du mois est plus courant au SN
            $table->boolean('renouvellement_automatique')->default(true); // Souvent tacite au SN

            $table->timestamps();

            $table->index(['statut', 'date_fin']);
            $table->index(['date_debut', 'date_fin']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baux');
    }
};

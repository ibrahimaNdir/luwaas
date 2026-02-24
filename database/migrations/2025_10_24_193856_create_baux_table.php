<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baux', function (Blueprint $table) {
            $table->id();

            // ═══════════════════════════════════════════════════════════
            // RELATIONS
            // ═══════════════════════════════════════════════════════════
            $table->foreignId('demande_id')->nullable()->constrained('demandes')->onDelete('set null');
            $table->foreignId('logement_id')->constrained('logements')->onDelete('cascade');
            $table->foreignId('locataire_id')->constrained('locataires')->onDelete('cascade');

            // ═══════════════════════════════════════════════════════════
            // FINANCES (Montants contractuels uniquement)
            // ═══════════════════════════════════════════════════════════
            $table->integer('montant_loyer');
            $table->integer('charges_mensuelles')->default(0);
            $table->integer('nombre_mois_caution');
            $table->integer('montant_caution_total');

            // ═══════════════════════════════════════════════════════════
            // DATES & ÉCHÉANCES
            // ═══════════════════════════════════════════════════════════
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('jour_echeance')->default(5);
            $table->boolean('renouvellement_automatique')->default(true);

            // ═══════════════════════════════════════════════════════════
            // STATUT DU BAIL
            // ═══════════════════════════════════════════════════════════
            $table->enum('statut', [
                'en_attente_paiement',  // Créé, attend signature/paiement
                'actif',                // Signé et actif
                'expire',               // Terminé
                'resilie',              // Résilié avant terme
                'suspendu',             // Suspendu
            ])->default('en_attente_paiement');

            // ═══════════════════════════════════════════════════════════
            // DOCUMENTS
            // ═══════════════════════════════════════════════════════════
            $table->string('document_pdf_path')->nullable(); // PDF final
            $table->string('document_scan_path')->nullable(); // Scan optionnel

            // ═══════════════════════════════════════════════════════════
            // CONDITIONS SPÉCIALES
            // ═══════════════════════════════════════════════════════════
            $table->text('conditions_speciales')->nullable();

            // ═══════════════════════════════════════════════════════════
            // TIMESTAMPS & INDEX
            // ═══════════════════════════════════════════════════════════
            $table->timestamps();

            $table->index(['statut', 'date_fin']);
            $table->index(['date_debut', 'date_fin']);
            $table->index('locataire_id');
            $table->index('logement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baux');
    }
};
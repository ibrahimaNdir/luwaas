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
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->onDelete('cascade');

            // ═══════════════════════════════════════════════════════════
            // FINANCES
            // ═══════════════════════════════════════════════════════════
            $table->integer('montant_loyer');
            $table->integer('charges_mensuelles')->default(0);
            $table->integer('nombre_mois_caution');
            $table->integer('montant_caution_total');

            // ← NOUVEAU : répartition de la caution (conformité légale)

            $table->decimal('montant_caution_signature', 12, 2);
            $table->decimal('montant_caution_etale', 12, 2)->default(0);
            $table->decimal('mensualite_caution', 12, 2)->default(0);
            $table->unsignedTinyInteger('mois_etalement_restants')->default(0);



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
                'en_attente_paiement',
                'actif',
                'expire',
                'resilie',
                'suspendu',
            ])->default('en_attente_paiement');

            $table->timestamp('date_activation')->nullable();

            // ═══════════════════════════════════════════════════════════
            // DOCUMENTS
            // ═══════════════════════════════════════════════════════════
            $table->string('document_pdf_path')->nullable();
            $table->string('document_scan_path')->nullable();

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

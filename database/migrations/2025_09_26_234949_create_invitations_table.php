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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();

            // Identifiant métier lisible (ex: INV-2025-001)
            $table->string('invitation_id')->unique()->index();

            // Relations
            $table->foreignId('proprietaire_id')
                ->constrained('proprietaires')
                ->onDelete('cascade');

            $table->foreignId('logement_id')
                ->constrained('logements')
                ->onDelete('cascade');

            // Si le locataire existe déjà dans la plateforme
            $table->foreignId('locataire_id')
                ->nullable()
                ->constrained('locataires')
                ->onDelete('set null');

            // Si le locataire n’a pas encore de compte
            $table->string('email_locataire')->nullable();

            // Statut de l’invitation
            $table->enum('statut', [
                'en_attente',
                'acceptee',
                'refusee',
                'expiree'
            ])->default('en_attente');

            // Sécurité et suivi
            $table->string('token')->nullable(); // jeton unique pour le lien d’invitation
            $table->timestamp('expire_at')->nullable(); // date limite de validité
            $table->text('message')->nullable(); // message personnalisé du propriétaire

            // Timestamps Laravel
            $table->timestamps();

            // Index utiles
            $table->index(['statut', 'expire_at']);
            $table->index('email_locataire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

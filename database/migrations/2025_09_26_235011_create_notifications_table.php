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
        Schema::create('notifications', function (Blueprint $table) {

            $table->id();

            // Destinataire de la notification
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Type de notification (utile pour filtrer)
            $table->string('type')->nullable();
            // ex: "paiement", "bail", "logement", "systeme"

            // Contenu
            $table->string('titre');   // résumé court
            $table->text('message');   // contenu détaillé

            // Statut de lecture
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Métadonnées
            $table->timestamp('sent_at')->nullable(); // date d’envoi
            $table->string('url')->nullable();        // lien vers une ressource (ex: /paiements/10)
            $table->json('data')->nullable();         // infos additionnelles (ex: {"paiement_id": 12})

            // Timestamps
            $table->timestamps();

            // Index utiles
            $table->index('is_read');
            $table->index('type');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

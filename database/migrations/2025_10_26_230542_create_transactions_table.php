<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // ═══════════════════════════════════════════════════════════
            // RELATION
            // ═══════════════════════════════════════════════════════════
            $table->foreignId('paiement_id')
                ->constrained('paiements')
                ->onDelete('cascade');

            // ═══════════════════════════════════════════════════════════
            // INFORMATIONS TRANSACTION
            // ═══════════════════════════════════════════════════════════
            $table->string('mode_paiement'); // wave, orange_money, free_money, paypal, espece
            $table->decimal('montant', 10, 2);
            
            // ═══════════════════════════════════════════════════════════
            // STATUT TRANSACTION
            // ═══════════════════════════════════════════════════════════
            $table->enum('statut', [
                'en_attente',  // Initié, pas encore confirmé
                'valide',      // Confirmé par webhook
                'rejete',      // Échoué
                'rembourse',   // Remboursé
            ])->default('en_attente');

            // ═══════════════════════════════════════════════════════════
            // DÉTAILS MOBILE MONEY
            // ═══════════════════════════════════════════════════════════
            $table->string('reference')->nullable()->unique(); // WV-123, OM-456, etc.
            $table->string('telephone_payeur')->nullable(); // +221771234567
            $table->string('ip_address')->nullable();
            $table->timestamp('date_transaction')->nullable();

            // ═══════════════════════════════════════════════════════════
            // MÉTADONNÉES (pour webhook)
            // ═══════════════════════════════════════════════════════════
            $table->text('metadata')->nullable(); // JSON pour infos supplémentaires

            // ═══════════════════════════════════════════════════════════
            // TIMESTAMPS & INDEX
            // ═══════════════════════════════════════════════════════════
            $table->timestamps();

            $table->index('paiement_id');
            $table->index('reference');
            $table->index('statut');
            $table->index('mode_paiement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

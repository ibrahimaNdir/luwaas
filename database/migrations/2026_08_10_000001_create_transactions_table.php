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
            // TYPE
            // ═══════════════════════════════════════════════════════════
            $table->enum('type', [
                'rent_payment',         // Paiement loyer / caution
                'subscription_payment', // Paiement abonnement SaaS
            ]);

            // ═══════════════════════════════════════════════════════════
            // RELATIONS (nullable selon le type)
            // rent_payment         → paiement_id renseigné, subscription_id null
            // subscription_payment → subscription_id renseigné, paiement_id null
            // ═══════════════════════════════════════════════════════════
            $table->foreignId('paiement_id')
                ->nullable()
                ->constrained('paiements')
                ->onDelete('cascade');

            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->onDelete('cascade');

            // ═══════════════════════════════════════════════════════════
            // RÉFÉRENCES
            // ═══════════════════════════════════════════════════════════
            $table->string('reference')->nullable()->unique();          // LOYER-1-5-ABC123
            $table->string('paydunyatoken')->nullable()->unique();  // token PayDunya
            $table->string('lien_paiement')->nullable();                // deeplink/URL PayDunya

            // ═══════════════════════════════════════════════════════════
            // INFORMATIONS PAIEMENT
            // ═══════════════════════════════════════════════════════════
            $table->string('mode_paiement'); // wave, orange_money, free_money
            $table->decimal('montant', 10, 2);

            // ═══════════════════════════════════════════════════════════
            // STATUT
            // ═══════════════════════════════════════════════════════════
            $table->enum('statut', [
                'en_attente', // Initié, en attente de confirmation PayDunya
                'valide',     // Confirmé par webhook
                'rejete',     // Échoué ou annulé
                'rembourse',  // Remboursé
            ])->default('en_attente');

            // ═══════════════════════════════════════════════════════════
            // DÉTAILS
            // ═══════════════════════════════════════════════════════════
            $table->string('telephone_payeur')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('date_transaction')->nullable();
            $table->timestamp('expire_at')->nullable(); // créée + 30 min, pour éviter les tokens fantômes

            // ═══════════════════════════════════════════════════════════
            // TIMESTAMPS & INDEX
            // ═══════════════════════════════════════════════════════════
            $table->timestamps();

            $table->index('type');
            $table->index('paiement_id');
            $table->index('subscription_id');
            $table->index('statut');
            $table->index('mode_paiement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
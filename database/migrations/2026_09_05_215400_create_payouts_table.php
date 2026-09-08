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
        if (!Schema::hasTable('payouts')) {
            Schema::create('payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proprietaire_id')->constrained('proprietaires')->onDelete('cascade');
                $table->decimal('gross_amount', 15, 2);           // Montant brut du loyer payé par le locataire
                $table->decimal('luwaas_commission', 15, 2);      // Commission commerciale de Luwaas (6% avec minimum 6000 FCFA)
                $table->decimal('payin_fee_absorbed', 15, 2);     // Frais d'encaissement (Payin) absorbés par Luwaas
                $table->decimal('payout_fee_absorbed', 15, 2);    // Frais de reversement (Payout) absorbés par Luwaas
                $table->decimal('net_amount_to_owner', 15, 2);    // Montant net dû au propriétaire (après commission Luwaas)
                $table->decimal('luwaas_net_benefit', 15, 2);     // Bénéfice net de Luwaas (commission moins frais absorbés)
                $table->foreignId('payout_method_id')->constrained('payout_methods')->onDelete('restrict');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
                $table->timestamp('processed_at')->nullable();
                $table->date('period_start');               // Début de la période considérée
                $table->date('period_end');                 // Fin de la période considérée
                $table->string('reference')->unique();      // Référence interne ou externe du versement
                $table->timestamps();

                // Index pour améliorer les performances des recherches fréquentes
                $table->index(['proprietaire_id', 'status']);
                $table->index(['period_start', 'period_end']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

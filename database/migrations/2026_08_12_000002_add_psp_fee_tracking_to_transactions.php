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
        Schema::table('transactions', function (Blueprint $table) {
            // Keep mode_paiement for backward compatibility, add new fields
            $table->string('gateway_used')->after('mode_paiement')->nullable()->comment('paydunya, bictorys, etc.');
            $table->string('operator_used')->after('gateway_used')->nullable()->comment('wave, orange_money, etc. - can differ from mode_paiement during transition');
            $table->enum('operation_type_used', ['payin', 'payout'])->after('operator_used')->nullable()->comment('Type d\'opération financière');

            // PSP fee tracking fields
            $table->decimal('psp_rate', 5, 4)->after('operation_type_used')->nullable()->comment('Taux PSP appliqué au moment de la transaction (ex: 0.015 pour 1.5%)');
            $table->decimal('psp_fee_expected', 15, 2)->after('psp_rate')->nullable()->comment('Frais PSP théorique calculé (montant * psp_rate)');
            $table->decimal('psp_fee_actual', 15, 2)->after('psp_fee_expected')->nullable()->comment('Frais PSP réellement facturés par le PSP, si disponible');
            $table->enum('psp_fee_status', ['expected', 'actual', 'estimated'])->after('psp_fee_actual')->nullable()->default('expected')->comment('Statut du frais PSP: expected/actual/estimated');

            // Link between PAYIN and PAYOUT transactions
            $table->unsignedBigInteger('payin_transaction_id')->after('psp_fee_status')->nullable()->comment('Reference to the PAYIN transaction for PAYOUT transactions');
            $table->foreign('payin_transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payin_transaction_id']);
            $table->dropColumn([
                'payin_transaction_id',
                'psp_fee_status',
                'psp_fee_actual',
                'psp_fee_expected',
                'psp_rate',
                'operation_type_used',
                'operator_used',
                'gateway_used'
            ]);
        });
    }
};
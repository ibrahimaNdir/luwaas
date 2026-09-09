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
        Schema::table('commission_rates', function (Blueprint $table) {
            // Add gateway column to differentiate between payment aggregators
            $table->string('gateway')->after('id')->nullable()->comment('paydunya, bictorys, cinetpay, etc.');

            // Add operation_type to differentiate between payin and payout operations
            $table->enum('operation_type', ['payin', 'payout'])->after('gateway')->comment('Type d\'opération financière');

            // Add composite unique index for gateway, operator, operation_type, valid_from
            // We'll create it directly without trying to drop an existing one that may not exist
            $table->unique(['gateway', 'operator', 'operation_type', 'valid_from'], 'commission_rates_gateway_operator_operation_type_valid_from_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_rates', function (Blueprint $table) {
            $table->dropUnique('commission_rates_gateway_operator_operation_type_valid_from_unique');
            $table->dropColumn(['operation_type', 'gateway']);
        });
    }
};
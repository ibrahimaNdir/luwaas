<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('transactions', 'gateway_used')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('gateway_used', ['paydunya', 'bictorys'])
                    ->after('paydunyatoken')
                    ->default('paydunya')
                    ->nullable()->changeFirst(); // We'll make it nullable first, then update, then change to not null
            });

            // Set gateway_used for existing rows (assuming PayDunya for all historical transactions)
            DB::table('transactions')->update(['gateway_used' => 'paydunya']);

            // Now change column to not null
            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('gateway_used', ['paydunya', 'bictorys'])
                    ->after('paydunyatoken')
                    ->default('paydunya')
                    ->change();
            });

            // Add index
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('gateway_used');
            });

            // Add check constraint (PostgreSQL)
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_gateway_used CHECK (gateway_used IN (\'paydunya\', \'bictorys\'))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraint
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_gateway_used');

        // Drop index
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['gateway_used']);
        });

        // Drop column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('gateway_used');
        });
    }
};
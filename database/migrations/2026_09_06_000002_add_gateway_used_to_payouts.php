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
        if (!Schema::hasColumn('payouts', 'payin_gateway_used')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->enum('payin_gateway_used', ['paydunya', 'bictorys'])
                    ->after('payout_fee_absorbed')
                    ->default('paydunya')
                    ->nullable();
                $table->enum('payout_gateway_used', ['paydunya', 'bictorys'])
                    ->after('payin_gateway_used')
                    ->default('paydunya')
                    ->nullable();
                $table->decimal('payin_fee_rate_applied', 5, 4)
                    ->after('payout_gateway_used')
                    ->default(0)
                    ->nullable();
                $table->decimal('payout_fee_rate_applied', 5, 4)
                    ->after('payin_fee_rate_applied')
                    ->default(0)
                    ->nullable();
                $table->decimal('luwaas_commission_rate_applied', 5, 4)
                    ->after('payout_fee_rate_applied')
                    ->default(0.0600)
                    ->nullable();
            });

            // Set default values for existing rows
            DB::table('payouts')->update([
                'payin_gateway_used' => 'paydunya',
                'payout_gateway_used' => 'paydunya',
                'payin_fee_rate_applied' => 0,
                'payout_fee_rate_applied' => 0,
                'luwaas_commission_rate_applied' => 0.0600,
            ]);

            // Now change columns to not null
            DB::statement('ALTER TABLE payouts ALTER COLUMN payin_gateway_used SET NOT NULL');
            DB::statement('ALTER TABLE payouts ALTER COLUMN payout_gateway_used SET NOT NULL');
            DB::statement('ALTER TABLE payouts ALTER COLUMN payin_fee_rate_applied SET NOT NULL');
            DB::statement('ALTER TABLE payouts ALTER COLUMN payout_fee_rate_applied SET NOT NULL');
            DB::statement('ALTER TABLE payouts ALTER COLUMN luwaas_commission_rate_applied SET NOT NULL');

            // Add indexes
            Schema::table('payouts', function (Blueprint $table) {
                $table->index('payin_gateway_used');
                $table->index('payout_gateway_used');
            });

            // Add unique constraint: (proprietaire_id, payin_gateway_used, payout_gateway_used)
            // (period_start and period_end columns are not present; adjust as needed)
            // Commented out unique constraint until columns are present
            // Schema::table('payouts', function (Blueprint $table) {
            //     $table->unique([
            //         'proprietaire_id',
            //         'period_start',
            //         'period_end',
            //         'payin_gateway_used',
            //         'payout_gateway_used'
            //     ], 'uniq_payout_period_gateway');
            // });

            // Add check constraints (PostgreSQL)
            DB::statement('ALTER TABLE payouts ADD CONSTRAINT chk_payouts_payin_gateway_used CHECK (payin_gateway_used IN (\'paydunya\', \'bictorys\'))');
            DB::statement('ALTER TABLE payouts ADD CONSTRAINT chk_payouts_payout_gateway_used CHECK (payout_gateway_used IN (\'paydunya\', \'bictorys\'))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints
        DB::statement('ALTER TABLE payouts DROP CONSTRAINT IF EXISTS chk_payouts_payin_gateway_used');
        DB::statement('ALTER TABLE payouts DROP CONSTRAINT IF EXISTS chk_payouts_payout_gateway_used');

        // Drop unique constraint (commented out as columns may not exist)
        // Schema::table('payouts', function (Blueprint $table) {
        //     $table->dropUnique(['uniq_payout_period_gateway']);
        // });

        // Drop indexes
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['payin_gateway_used']);
            $table->dropIndex(['payout_gateway_used']);
        });

        // Drop columns
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'luwaas_commission_rate_applied',
                'payout_fee_rate_applied',
                'payin_fee_rate_applied',
                'payout_gateway_used',
                'payin_gateway_used'
            ]);
        });
    }
};
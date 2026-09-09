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
        // Fix transaction type enum - add 'rent_payout' (PostgreSQL only)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_type WHERE typname = 'transactions_type_enum')") === 'true') {
                DB::statement("ALTER TYPE transactions_type_enum ADD VALUE IF NOT EXISTS 'rent_payout'");
            }
        }

        // Fix transaction statut enum - add 'completed' (PostgreSQL only)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_type WHERE typname = 'transactions_statut_enum')") === 'true') {
                DB::statement("ALTER TYPE transactions_statut_enum ADD VALUE IF NOT EXISTS 'completed'");
            }
        }

        // Fix subscription status enum - add 'failed' (PostgreSQL only)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_type WHERE typname = 'subscriptions_status_enum')") === 'true') {
                DB::statement("ALTER TYPE subscriptions_status_enum ADD VALUE IF NOT EXISTS 'failed'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Removing enum values is not straightforward in PostgreSQL
        // and would require recreating the type. Since this is unlikely to be needed,
        // we'll make this migration irreversible for simplicity.
        // In a production environment, you would want to handle this more carefully.
    }
};
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
        // Check if column doesn't exist before adding it
        if (!Schema::hasColumn('proprietaires', 'payout_channel_preference')) {
            Schema::table('proprietaires', function (Blueprint $table) {
                $table->enum('payout_channel_preference', ['wave', 'orange_money', 'free_money', 'bank_transfer'])->nullable()->after('telephone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping it
        if (Schema::hasColumn('proprietaires', 'payout_channel_preference')) {
            Schema::table('proprietaires', function (Blueprint $table) {
                $table->dropColumn('payout_channel_preference');
            });
        }
    }
};
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
        // Check if the old column exists before trying to rename it
        if (Schema::hasColumn('transactions', 'paydunyatoken')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('paydunyatoken', 'gateway_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the new column exists before trying to rename it back
        if (Schema::hasColumn('transactions', 'gateway_token')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('gateway_token', 'paydunyatoken');
            });
        }
    }
};
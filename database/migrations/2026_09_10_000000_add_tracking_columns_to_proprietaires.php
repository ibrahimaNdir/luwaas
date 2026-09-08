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
        Schema::table('proprietaires', function (Blueprint $table) {
            $table->timestamp('expiration_notified_at')->nullable()->after('subscription_ends_at');
            $table->timestamp('choice_notified_at')->nullable()->after('expiration_notified_at');
            $table->timestamp('choice_made_at')->nullable()->after('choice_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proprietaires', function (Blueprint $table) {
            $table->dropColumn(['expiration_notified_at', 'choice_notified_at', 'choice_made_at']);
        });
    }
};
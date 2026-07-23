<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->timestamp('date_refus')->nullable()->after('date_acceptation');
            $table->timestamp('date_non_aboutie')->nullable()->after('date_refus');
            $table->timestamp('date_bail_cree')->nullable()->after('date_non_aboutie');
            $table->string('motif_refus')->nullable()->after('date_bail_cree');
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropColumn(['date_refus', 'date_non_aboutie', 'date_bail_cree', 'motif_refus']);
        });
    }
};
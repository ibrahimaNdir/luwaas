<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_base_xof', 10, 2)->default(0)->after('billing_cycle');
            $table->decimal('price_per_property_xof', 10, 2)->default(0)->after('price_base_xof');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_base_xof', 'price_per_property_xof']);
        });
    }
};

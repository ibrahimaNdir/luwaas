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
        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();
            $table->string('operator')->comment('wave, orange_money, free_money, etc.');
            $table->float('rate_percent')->comment('Taux en pourcentage (ex: 1.5 pour 1.5%)');
            $table->float('fixed_fee')->default(0)->comment('Frais fixes en monnaie locale');
            $table->date('valid_from')->comment('Date de début de validité');
            $table->date('valid_to')->nullable()->comment('Date de fin de validité (null = illimitée)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_rates');
    }
};
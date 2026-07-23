<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            // ex: 'free', 'pro-monthly', 'pro-yearly'

            $table->string('name');
            // ex: 'Gratuit', 'Pro'

            $table->string('tier');
            // Valeurs : free | pro

            $table->string('billing_cycle')->nullable();
            // Valeurs : null (free) | monthly | yearly

            $table->decimal('price_xof', 10, 2)->default(0);
            // Prix en FCFA — 0 pour le plan free

            $table->unsignedInteger('publications_max')->nullable();
            // 1 pour free | 10 pour pro | null = illimité

            $table->json('features')->nullable();
            // ex: ["Quittances PDF", "Rappels SMS", "Export Excel"]

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

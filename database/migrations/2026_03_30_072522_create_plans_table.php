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
            // ex: 'starter-monthly', 'pro-yearly'

            $table->string('name');
            // ex: 'Starter', 'Pro', 'Enterprise'

            $table->enum('tier', ['starter', 'pro', 'enterprise']);
            // pour regrouper les plans du même niveau

            $table->enum('billing_cycle', ['monthly', 'yearly'])
                  ->default('monthly');           // ✅ ajout

            $table->decimal('price_xof', 10, 2)->default(0);
            // Prix en FCFA selon le billing_cycle

            $table->integer('biens_max')->nullable();
            // null = illimité

            $table->integer('locataires_max')->nullable();
            // ✅ renommé, null = illimité

            $table->integer('cogestionnaires_max')->nullable();
            // ✅ ajout, 1 = seulement le propriétaire lui-même

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
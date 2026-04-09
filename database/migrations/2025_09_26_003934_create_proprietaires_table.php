<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proprietaires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('proprietaire_id')->unique();
            $table->string('cni')->unique();
            $table->boolean('is_actif')->default(true);

            // Subscription
            $table->timestamp('trial_ends_at')->nullable();
            $table->enum('subscription_status', [
                'trial',
                'active',
                'expired',
                'cancelled'
            ])->default('trial');

            $table->enum('plan', [
                'starter',
                'pro',
                'enterprise'
            ])->nullable();                                // null = pas encore de plan payant

            $table->enum('billing_cycle', [
                'monthly',
                'yearly'
            ])->nullable();                                // ✅ ajout

            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); // ✅ ajout

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietaires');
    }
};
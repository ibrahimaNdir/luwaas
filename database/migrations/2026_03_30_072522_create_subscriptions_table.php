<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proprietaire_id')
                  ->constrained('proprietaires')
                  ->onDelete('cascade');

            $table->foreignId('plan_id')
                  ->constrained('plans')
                  ->onDelete('restrict');

            $table->enum('status', [
                'pending',    // ✅ paiement initié
                'active',
                'cancelled',
                'expired'
            ])->default('pending'); // ✅ default = pending

            $table->enum('payment_gateway', [
                'paydunya'   // aujourd'hui seulement PayDunya, extensible demain
            ])->default('paydunya');

            $table->enum('payment_method', [
                'wave',
                'orange_money',
                'free_money',
                'card'
            ])->nullable(); // nullable car connu seulement après paiement

            $table->string('paydunya_token')->nullable()->unique(); // ✅ pour le webhook IPN
            $table->string('transaction_ref')->nullable();
            $table->decimal('amount', 10, 2);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('cancelled_at')->nullable(); // ✅ traçabilité

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
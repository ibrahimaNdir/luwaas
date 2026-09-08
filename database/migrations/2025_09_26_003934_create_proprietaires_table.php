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
                ->cascadeOnDelete();

            $table->string('proprietaire_id')->unique();
            $table->boolean('is_actif')->default(true);

            // Etat courant d'abonnement
            $table->string('subscription_status')->default('free_trial');
            // free_trial | pending_payment | active | expired | cancelled

            $table->string('plan')->nullable();
            // free | pro

            $table->string('billing_cycle')->nullable();
            // null | monthly | yearly

            $table->unsignedTinyInteger('publications_actives')->default(0);

            // Gratuit temporaire de 15 jours
            $table->timestamp('trial_ends_at')->nullable();

            // Fin du plan payant pro
            $table->timestamp('subscription_ends_at')->nullable();

            // Annulation programmée ou historique
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietaires');
    }
};
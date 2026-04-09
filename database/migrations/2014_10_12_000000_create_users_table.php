<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('prenom');
            $table->string('nom');
            $table->string('telephone')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->enum('user_type', ['proprietaire', 'locataire', 'admin']);
            $table->json('profile')->nullable();
            $table->rememberToken();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('phone_otp', 6)->nullable();
            $table->timestamp('phone_otp_expires_at')->nullable();
            $table->timestamps();


            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->softDeletes(); // Ajoute deleted_at (timestamp) + index standard
            $table->index(['deleted_at', 'propriete_id']); // Index optimisé pour requêtes courantes
        });
    }

    public function down(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['deleted_at', 'propriete_id']);
        });
    }
};

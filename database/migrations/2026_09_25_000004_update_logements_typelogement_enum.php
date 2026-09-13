<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the enum type for logements.typelogement to include new values
        // Using the safe column replacement approach

        // Check if table exists
        if (Schema::hasTable('logements')) {
            // Check if typelogement column exists
            if (Schema::hasColumn('logements', 'typelogement')) {
                // Add new temporary column with updated enum
                Schema::table('logements', function (Blueprint $table) {
                    $table->enum('typelogement_new', ['studio','appartement','maison','villa','chambre','bureau','local_commercial','magasin'])->nullable();
                });

                // Copy data from old column to new column
                DB::table('logements')->update(['typelogement_new' => DB::raw('typelogement')]);

                // Drop old column
                Schema::table('logements', function (Blueprint $table) {
                    $table->dropColumn('typelogement');
                });

                // Rename new column to original name
                Schema::table('logements', function (Blueprint $table) {
                    $table->renameColumn('typelogement_new', 'typelogement');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For rollback, revert to original enum
        if (Schema::hasTable('logements')) {
            if (Schema::hasColumn('logements', 'typelogement')) {
                // Add new temporary column with original enum
                Schema::table('logements', function (Blueprint $table) {
                    $table->enum('typelogement_new', ['studio','appartement','maison','villa'])->nullable();
                });

                // Copy data - handle rows that might have the new values
                DB::table('logements')->update([
                    'typelogement_new' => DB::raw("CASE
                        WHEN typelogement IN ('studio', 'appartement', 'maison', 'villa') THEN typelogement
                        ELSE 'studio'
                    END")
                ]);

                // Drop old column
                Schema::table('logements', function (Blueprint $table) {
                    $table->dropColumn('typelogement');
                });

                // Rename new column to original name
                Schema::table('logements', function (Blueprint $table) {
                    $table->renameColumn('typelogement_new', 'typelogement');
                });
            }
        }
    }
};
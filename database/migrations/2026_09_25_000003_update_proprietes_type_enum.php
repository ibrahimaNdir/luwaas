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
        // Update the enum type for proprietes.type to include new values
        // Using the safe column replacement approach

        // Check if table exists
        if (Schema::hasTable('proprietes')) {
            // Check if type column exists
            if (Schema::hasColumn('proprietes', 'type')) {
                // Add new temporary column with updated enum
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->enum('type_new', ['maison','immeuble','villa','appartement','local_commercial','bureau','magasin'])->nullable();
                });

                // Copy data from old column to new column
                DB::table('proprietes')->update(['type_new' => DB::raw('type')]);

                // Drop old column
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->dropColumn('type');
                });

                // Rename new column to original name
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->renameColumn('type_new', 'type');
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
        if (Schema::hasTable('proprietes')) {
            if (Schema::hasColumn('proprietes', 'type')) {
                // Add new temporary column with original enum
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->enum('type_new', ['maison','immeuble','villa'])->nullable();
                });

                // Copy data - handle rows that might have the new values
                DB::table('proprietes')->update([
                    'type_new' => DB::raw("CASE
                        WHEN type IN ('maison', 'immeuble', 'villa') THEN type
                        ELSE 'maison'
                    END")
                ]);

                // Drop old column
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->dropColumn('type');
                });

                // Rename new column to original name
                Schema::table('proprietes', function (Blueprint $table) {
                    $table->renameColumn('type_new', 'type');
                });
            }
        }
    }
};
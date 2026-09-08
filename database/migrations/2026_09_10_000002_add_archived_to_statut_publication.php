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
        // Create enum type if it doesn't exist
        DB::statement("DO $$ BEGIN
            CREATE TYPE statut_publication_enum AS ENUM('brouillon','publie','archivé');
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        // Drop default if exists
        DB::statement("DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'logements' AND column_name = 'statut_publication' AND column_default IS NOT NULL) THEN
                    EXECUTE 'ALTER TABLE logements ALTER COLUMN statut_publication DROP DEFAULT';
                END IF;
            END
        $$;");

        // Alter column to use the new enum type
        DB::statement("ALTER TABLE logements ALTER COLUMN statut_publication TYPE statut_publication_enum USING statut_publication::text::statut_publication_enum;");
        // Set default
        DB::statement("ALTER TABLE logements ALTER COLUMN statut_publication SET DEFAULT 'brouillon';");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum (brouillon, publie)
        DB::statement("DO $$ BEGIN
            CREATE TYPE statut_publication_enum_old AS ENUM('brouillon','publie');
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");

        // Drop default if exists
        DB::statement("DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'logements' AND column_name = 'statut_publication' AND column_default IS NOT NULL) THEN
                    EXECUTE 'ALTER TABLE logements ALTER COLUMN statut_publication DROP DEFAULT';
                END IF;
            END
        $$;");

        DB::statement("ALTER TABLE logements ALTER COLUMN statut_publication TYPE statut_publication_enum_old USING statut_publication::text::statut_publication_enum_old;");
        DB::statement("ALTER TABLE logements ALTER COLUMN statut_publication SET DEFAULT 'brouillon';");

        // Drop the enum types we created (optional)
        DB::statement("DROP TYPE IF EXISTS statut_publication_enum;");
        DB::statement("DROP TYPE IF EXISTS statut_publication_enum_old;");
    }
};
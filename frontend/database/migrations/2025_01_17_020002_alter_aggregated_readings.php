<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aggregated_readings')) {
            return;
        }

        // Normalize column types for compatibility with Java backend (expects VARCHAR(10) for period)
        DB::statement("ALTER TABLE aggregated_readings MODIFY COLUMN period VARCHAR(10) NOT NULL DEFAULT 'hour'");
        DB::statement("ALTER TABLE aggregated_readings MODIFY COLUMN avg DOUBLE NULL");
        DB::statement("ALTER TABLE aggregated_readings MODIFY COLUMN min DOUBLE NULL");
        DB::statement("ALTER TABLE aggregated_readings MODIFY COLUMN max DOUBLE NULL");
        DB::statement("ALTER TABLE aggregated_readings MODIFY COLUMN cnt INT NULL");
    }

    public function down(): void
    {
        // no-op
    }
};

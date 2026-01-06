<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sensor_readings')) {
            return;
        }

        // Java backend așteaptă float/double; în MySQL schimbăm coloana la DOUBLE.
        DB::statement("ALTER TABLE sensor_readings MODIFY COLUMN value DOUBLE NOT NULL");
    }

    public function down(): void
    {
        // No-op pentru a evita pierderea de date; nu reîntoarcem la DECIMAL implicit.
    }
};

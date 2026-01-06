<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_tokens')) {
            return;
        }

        Schema::table('api_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('api_tokens', 'active')) {
                $table->boolean('active')->default(true)->after('name');
            }
        });
    }

    public function down(): void
    {
        // Nu ștergem coloana la down pentru a evita pierderea datelor.
    }
};

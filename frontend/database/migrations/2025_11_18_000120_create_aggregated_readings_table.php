<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->enum('period', ['hour', 'day', 'week']);
            $table->timestamp('bucket_start');
            $table->decimal('avg_value', 10, 3)->nullable();
            $table->decimal('min_value', 10, 3)->nullable();
            $table->decimal('max_value', 10, 3)->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['sensor_id', 'period', 'bucket_start']);
            $table->index(['sensor_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_readings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aggregated_readings')) {
            return;
        }

        Schema::create('aggregated_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->string('period', 10)->default('hour');
            $table->double('avg')->nullable();
            $table->double('min')->nullable();
            $table->double('max')->nullable();
            $table->integer('cnt')->nullable();
            $table->unique(['sensor_id', 'bucket_start', 'period']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_readings');
    }
};

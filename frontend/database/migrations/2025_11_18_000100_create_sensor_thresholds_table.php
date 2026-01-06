<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->nullable()->constrained('sensors')->cascadeOnDelete();
            $table->string('sensor_type')->nullable(); // fallback by type when sensor_id is null
            $table->enum('direction', ['above', 'below']);
            $table->decimal('value', 10, 2);
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_telegram')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['sensor_id']);
            $table->index(['sensor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_thresholds');
    }
};

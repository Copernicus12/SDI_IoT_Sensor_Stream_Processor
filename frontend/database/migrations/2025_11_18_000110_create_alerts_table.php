<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->foreignId('sensor_reading_id')->nullable()->constrained('sensor_readings')->nullOnDelete();
            $table->string('sensor_type');
            $table->enum('direction', ['above', 'below']);
            $table->decimal('threshold_value', 10, 2);
            $table->decimal('actual_value', 10, 2);
            $table->enum('status', ['new', 'confirmed', 'resolved'])->default('new');
            $table->json('notified_channels')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['sensor_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};

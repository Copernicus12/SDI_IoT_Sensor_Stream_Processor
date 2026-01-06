<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->string('node_id')->unique(); // esp32_node1, esp32_node2, etc.
            $table->string('sensor_type'); // temperatura, umiditate, curent, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->nullable(); // °C, %, A, etc.
            $table->string('mqtt_topic'); // iot/esp32_node1/temperatura
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('node_id');
            $table->index('sensor_type');
            $table->index('mqtt_topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};

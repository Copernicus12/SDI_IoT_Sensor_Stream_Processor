<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            // Allow multiple sensors per node; keep uniqueness on topic and per type within a node.
            $table->dropUnique(['node_id']);
            $table->unique(['node_id', 'sensor_type']);
            $table->unique(['mqtt_topic']);
        });
    }

    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropUnique(['node_id', 'sensor_type']);
            $table->dropUnique(['mqtt_topic']);
            $table->unique(['node_id']);
        });
    }
};

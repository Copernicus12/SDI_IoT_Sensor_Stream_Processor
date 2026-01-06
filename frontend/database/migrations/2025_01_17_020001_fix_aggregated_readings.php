<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aggregated_readings')) {
            return;
        }

        Schema::table('aggregated_readings', function (Blueprint $table) {
            if (!Schema::hasColumn('aggregated_readings', 'bucket_start')) {
                $table->timestamp('bucket_start')->after('sensor_id');
            }
            if (!Schema::hasColumn('aggregated_readings', 'period')) {
                $table->string('period', 10)->default('hour')->after('bucket_start');
            }
            if (!Schema::hasColumn('aggregated_readings', 'avg')) {
                $table->double('avg')->nullable()->after('period');
            }
            if (!Schema::hasColumn('aggregated_readings', 'min')) {
                $table->double('min')->nullable()->after('avg');
            }
            if (!Schema::hasColumn('aggregated_readings', 'max')) {
                $table->double('max')->nullable()->after('min');
            }
            if (!Schema::hasColumn('aggregated_readings', 'cnt')) {
                $table->integer('cnt')->nullable()->after('max');
            }
            if (!Schema::hasColumn('aggregated_readings', 'created_at')) {
                $table->timestamps();
            }
            // Ensure unique index exists
            $table->unique(['sensor_id', 'bucket_start', 'period'], 'agg_unique_idx');
        });
    }

    public function down(): void
    {
        // No-op: we don't want to drop columns on rollback to avoid data loss
    }
};

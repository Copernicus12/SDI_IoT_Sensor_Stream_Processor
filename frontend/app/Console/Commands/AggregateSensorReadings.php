<?php

namespace App\Console\Commands;

use App\Models\AggregatedReading;
use App\Models\Sensor;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateSensorReadings extends Command
{
    protected $signature = 'sensors:aggregate {--period=hour} {--hours=48}';
    protected $description = 'Compute aggregates (avg/min/max/count) for sensor readings by period';

    public function handle(): int
    {
        $period = $this->option('period');
        $hours = (int) $this->option('hours');
        $start = now()->subHours($hours);

        $sensors = Sensor::all();
        foreach ($sensors as $sensor) {
            $this->aggregateForSensor($sensor->id, $period, $start);
        }
        $this->info('Aggregates computed.');
        return self::SUCCESS;
    }

    protected function aggregateForSensor(int $sensorId, string $period, Carbon $start): void
    {
        $bucketFormat = match ($period) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d 00:00:00',
            'week' => '%x-%v-1 00:00:00', // ISO week start
            default => '%Y-%m-%d %H:00:00',
        };

        $rows = SensorReading::query()
            ->where('sensor_id', $sensorId)
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '{$bucketFormat}') as bucket_start,
                         AVG(value) as avg_value, MIN(value) as min_value, MAX(value) as max_value, COUNT(*) as cnt")
            ->groupBy('bucket_start')
            ->orderBy('bucket_start')
            ->get();

        foreach ($rows as $row) {
            AggregatedReading::updateOrCreate(
                [
                    'sensor_id' => $sensorId,
                    'period' => $period,
                    'bucket_start' => $row->bucket_start,
                ],
                [
                    'avg_value' => $row->avg_value,
                    'min_value' => $row->min_value,
                    'max_value' => $row->max_value,
                    'count' => $row->cnt,
                ]
            );
        }
    }
}

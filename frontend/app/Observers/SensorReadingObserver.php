<?php

namespace App\Observers;

use App\Models\Alert;
use App\Models\SensorReading;
use App\Models\SensorThreshold;
use App\Services\AlertNotifier;

class SensorReadingObserver
{
    public function created(SensorReading $reading): void
    {
        $sensor = $reading->sensor()->first();
        if (!$sensor) return;

        // Find thresholds: specific sensor first, then by type
        $thresholds = SensorThreshold::query()
            ->where(function ($q) use ($sensor) {
                $q->where('sensor_id', $sensor->id)
                  ->orWhere(function ($q2) use ($sensor) {
                      $q2->whereNull('sensor_id')->where('sensor_type', $sensor->sensor_type);
                  });
            })
            ->where('enabled', true)
            ->get();

        foreach ($thresholds as $t) {
            $triggered = ($t->direction === 'above' && $reading->value > $t->value)
                      || ($t->direction === 'below' && $reading->value < $t->value);
            if (!$triggered) continue;

            $alert = Alert::create([
                'sensor_id' => $sensor->id,
                'sensor_reading_id' => $reading->id,
                'sensor_type' => $sensor->sensor_type,
                'direction' => $t->direction,
                'threshold_value' => $t->value,
                'actual_value' => $reading->value,
                'status' => 'new',
            ]);

            // Notify
            app(AlertNotifier::class)->notify($alert, $sensor, $t);
        }
    }
}

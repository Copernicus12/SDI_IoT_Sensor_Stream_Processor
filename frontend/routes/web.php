<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Protected dashboards
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $sensors = \App\Models\Sensor::with(['latestReading'])->get()->map(function ($sensor) {
            $isOnline = false;
            // Get readings for today for stats
            $todayReadings = $sensor->readings()
                ->whereDate('created_at', now()->today())
                ->get();
            
            $todayMin = $todayReadings->min('value');
            $todayMax = $todayReadings->max('value');
            $todayAvg = $todayReadings->avg('value');

            if ($sensor->latestReading) {
                // Consider online if data received in last 5 minutes
                $isOnline = $sensor->latestReading->created_at->gt(now()->subMinutes(5));
            }

            // Calculate trend (compare current vs last hour avg)
            $trend = 'stable';
            if ($sensor->latestReading && $todayAvg) {
                 if ($sensor->latestReading->value > $todayAvg * 1.05) $trend = 'up';
                 elseif ($sensor->latestReading->value < $todayAvg * 0.95) $trend = 'down';
            }

            return [
                'id' => $sensor->id,
                'name' => $sensor->name ?? $sensor->sensor_type,
                'type' => $sensor->sensor_type,
                'unit' => $sensor->unit,
                'is_online' => $isOnline,
                'value' => $sensor->latestReading ? $sensor->latestReading->value : null,
                'last_update' => $sensor->latestReading ? $sensor->latestReading->created_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i:s') : 'Never',
                'stats' => [
                    'min' => $todayMin !== null ? round($todayMin, 1) : '--',
                    'max' => $todayMax !== null ? round($todayMax, 1) : '--',
                    'avg' => $todayAvg !== null ? round($todayAvg, 1) : '--',
                ],
                'trend' => $trend,
            ];
        });

        $recentReadings = \App\Models\SensorReading::with('sensor')
            ->latest()
            ->take(2400) // Approx 10 mins of history for 4 sensors
            ->get()
            ->groupBy(function($r) {
                // Group by Minute + Sensor
                return $r->created_at->setTimezone('Europe/Bucharest')->format('Y-m-d H:i') . '|' . $r->sensor_id;
            })
            ->map(function($readings) {
                $first = $readings->first();
                return [
                    'key' => $first->created_at->setTimezone('Europe/Bucharest')->format('Y-m-d H:i') . '-' . $first->sensor_id,
                    'time' => $first->created_at->setTimezone('Europe/Bucharest')->format('H:i'),
                    'full_time' => $first->created_at->setTimezone('Europe/Bucharest')->format('Y-m-d H:i'),
                    'sensor_id' => $first->sensor_id,
                    'sensor_name' => $first->sensor->name ?? $first->sensor->sensor_type,
                    'unit' => $first->sensor->unit,
                    'avg_value' => round($readings->avg('value'), 2),
                    'count' => $readings->count(),
                ];
            })
            ->values()
            // Sort by time desc
            ->sortByDesc('full_time')
            // Get last 60 entries (approx 20 minutes of history for 3 sensors)
            ->take(60)
            ->values();

        // Ensure array
        if (!$recentReadings) {
            $recentReadings = [];
        }

        $recentAlerts = \App\Models\Alert::where('status', 'active') // Or remove 'active' if you want history
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($alert) {
                 return [
                     'id' => $alert->id,
                     'message' => "{$alert->sensor_type} reached {$alert->actual_value}", 
                     'threshold' => $alert->threshold_value,
                     'type' => $alert->direction === 'above' ? 'high' : 'low',
                     'time' => $alert->created_at->diffForHumans(),
                 ];
            });

        $systemStats = [
            'total_readings_today' => \App\Models\SensorReading::whereDate('created_at', now()->today())->count(),
            'active_alerts' => \App\Models\Alert::where('status', 'active')->count(),
        ];

        return Inertia::render('Dashboard', [
            'sensors' => $sensors,
            'recentReadings' => $recentReadings,
            'recentAlerts' => $recentAlerts,
            'systemStats' => $systemStats,
        ]);
    })->name('dashboard');

    // Device-specific dashboards
    Route::get('dashboard/dht11', function () {
        return Inertia::render('Sensors/DHT11');
    })->name('dashboard.dht11');

    Route::get('dashboard/soil', function () {
        return Inertia::render('Sensors/Soil');
    })->name('dashboard.soil');

    Route::get('dashboard/acs', function () {
        return Inertia::render('Sensors/ACS');
    })->name('dashboard.acs');

    // Trends page
    Route::get('dashboard/trends', function () {
        return Inertia::render('Trends');
    })->name('dashboard.trends');

    // Anomalies & Export pages
    Route::get('dashboard/anomalies', function () {
        return Inertia::render('Anomalies');
    })->name('dashboard.anomalies');

    Route::get('dashboard/export', function () {
        return Inertia::render('Export');
    })->name('dashboard.export');

    Route::get('/readings/details', function(\Illuminate\Http\Request $request) {
        $timeStr = $request->query('time'); // Expected: Y-m-d H:i
        $sensorId = $request->query('sensor_id');

        if (!$timeStr || !$sensorId) {
            return response()->json([]);
        }

        try {
            // Parse time in Bucharest time, then convert to UTC for DB query
            $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $timeStr, 'Europe/Bucharest');
            $start = $date->copy()->startOfMinute()->setTimezone('UTC');
            $end = $date->copy()->endOfMinute()->setTimezone('UTC');

            $readings = \App\Models\SensorReading::where('sensor_id', $sensorId)
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('created_at')
                ->get()
                ->map(function($r) {
                    return [
                        'time' => $r->created_at->setTimezone('Europe/Bucharest')->format('H:i:s'),
                        'value' => $r->value,
                    ];
                });

            return response()->json($readings);
        } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage()], 400);
        }
    })->name('readings.details');
});

require __DIR__.'/settings.php';
require __DIR__.'/api.php';

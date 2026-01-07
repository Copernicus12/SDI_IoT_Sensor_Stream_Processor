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
                'last_update' => $sensor->latestReading ? $sensor->latestReading->created_at->diffForHumans() : 'Never',
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
            ->take(10)
            ->get()
            ->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'sensor' => $reading->sensor->name ?? $reading->sensor->sensor_type,
                    'value' => $reading->value,
                    'unit' => $reading->sensor->unit,
                    'time' => $reading->created_at->format('H:i:s'),
                ];
            });

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
});

require __DIR__.'/settings.php';
require __DIR__.'/api.php';

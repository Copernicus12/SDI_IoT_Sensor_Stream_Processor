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
        return Inertia::render('Sensors/Dashboard');
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

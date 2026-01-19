<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingsController extends Controller
{
    public function edit(AppSettingsService $settings): Response
    {
        $defaults = $settings->defaults();

        return Inertia::render('settings/AppSettings', [
            'settings' => [
                'energy' => [
                    'price_per_kwh' => $settings->getFloat('energy.price_per_kwh', $defaults['energy']['price_per_kwh']),
                    'currency' => $settings->getString('energy.currency', $defaults['energy']['currency']),
                    'mains_voltage_v' => $settings->getFloat('energy.mains_voltage_v', $defaults['energy']['mains_voltage_v']),
                    'power_factor' => $settings->getFloat('energy.power_factor', $defaults['energy']['power_factor']),
                ],
                'distributed' => [
                    'window_minutes' => $settings->getInt('distributed.window_minutes', $defaults['distributed']['window_minutes']),
                    'z_warn' => $settings->getFloat('distributed.z_warn', $defaults['distributed']['z_warn']),
                    'z_critical' => $settings->getFloat('distributed.z_critical', $defaults['distributed']['z_critical']),
                    'staleness_threshold_s' => $settings->getInt('distributed.staleness_threshold_s', $defaults['distributed']['staleness_threshold_s']),
                ],
            ],
        ]);
    }

    public function update(Request $request, AppSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'energy.price_per_kwh' => ['required', 'numeric', 'min:0'],
            'energy.currency' => ['required', 'string', 'in:RON,EUR,USD'],
            'energy.mains_voltage_v' => ['required', 'numeric', 'min:50', 'max:400'],
            'energy.power_factor' => ['required', 'numeric', 'min:0.1', 'max:1.0'],

            'distributed.window_minutes' => ['required', 'integer', 'min:10', 'max:360'],
            'distributed.z_warn' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'distributed.z_critical' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'distributed.staleness_threshold_s' => ['required', 'integer', 'min:10', 'max:3600'],
        ]);

        $settings->setMany([
            'energy.price_per_kwh' => data_get($validated, 'energy.price_per_kwh'),
            'energy.currency' => data_get($validated, 'energy.currency'),
            'energy.mains_voltage_v' => data_get($validated, 'energy.mains_voltage_v'),
            'energy.power_factor' => data_get($validated, 'energy.power_factor'),

            'distributed.window_minutes' => data_get($validated, 'distributed.window_minutes'),
            'distributed.z_warn' => data_get($validated, 'distributed.z_warn'),
            'distributed.z_critical' => data_get($validated, 'distributed.z_critical'),
            'distributed.staleness_threshold_s' => data_get($validated, 'distributed.staleness_threshold_s'),
        ]);

        return to_route('app-settings.edit');
    }

    public function getAnomalySettings(AppSettingsService $settings)
    {
        $z = $settings->getFloat('anomaly.z_threshold', 3.0);
        $window = $settings->getInt('anomaly.window_size', 30);
        $hours = $settings->getInt('anomaly.lookback_hours', 6);

        return response()->json([
            'success' => true,
            'data' => [
                'z' => $z,
                'window' => $window,
                'hours' => $hours,
            ],
        ]);
    }

    public function saveAnomalySettings(Request $request, AppSettingsService $settings)
    {
        $validated = $request->validate([
            'z' => ['required', 'numeric', 'min:1', 'max:5'],
            'window' => ['required', 'integer', 'min:10', 'max:100'],
            'hours' => ['required', 'integer', 'min:1', 'max:48'],
        ]);

        $settings->setMany([
            'anomaly.z_threshold' => $validated['z'],
            'anomaly.window_size' => $validated['window'],
            'anomaly.lookback_hours' => $validated['hours'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anomaly detection settings saved successfully',
        ]);
    }
}

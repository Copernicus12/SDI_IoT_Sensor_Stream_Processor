<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class AppSettingsService
{
    /**
     * Defaults are intentionally conservative and safe.
     */
    public function defaults(): array
    {
        return [
            'energy' => [
                'price_per_kwh' => 1.0,
                'currency' => 'RON',
                'mains_voltage_v' => 230.0,
                'power_factor' => 1.0,
            ],
            'distributed' => [
                'window_minutes' => 60,
                'z_warn' => 2.0,
                'z_critical' => 3.0,
                'staleness_threshold_s' => 180,
            ],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        try {
            $row = AppSetting::query()->where('key', $key)->first();
        } catch (QueryException) {
            return $default;
        }
        if (!$row) {
            return $default;
        }

        // We store JSON; allow scalars by using {"v": <scalar>}
        $value = $row->value;
        if (is_array($value) && array_key_exists('v', $value)) {
            return $value['v'];
        }

        return $value;
    }

    public function getFloat(string $key, float $default): float
    {
        $v = $this->get($key, $default);
        if (is_array($v)) {
            return $default;
        }
        return is_numeric($v) ? (float) $v : $default;
    }

    public function getInt(string $key, int $default): int
    {
        $v = $this->get($key, $default);
        if (is_array($v)) {
            return $default;
        }
        return is_numeric($v) ? (int) $v : $default;
    }

    public function getString(string $key, string $default): string
    {
        $v = $this->get($key, $default);
        return is_string($v) ? $v : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $payload = is_array($value) ? $value : ['v' => $value];

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $payload],
        );
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }
}

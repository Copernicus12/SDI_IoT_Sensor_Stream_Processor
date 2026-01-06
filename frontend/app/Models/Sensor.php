<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'sensor_type',
        'name',
        'description',
        'unit',
        'is_active',
        'mqtt_topic',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all readings for this sensor
     */
    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    /**
     * Get the latest reading
     */
    public function latestReading()
    {
        return $this->hasOne(SensorReading::class)->latestOfMany();
    }

    /**
     * Get readings from the last hours
     */
    public function recentReadings(int $hours = 24)
    {
        return $this->readings()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get statistics for this sensor
     */
    public function getStatistics(int $hours = 24)
    {
        return $this->readings()
            ->where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('
                AVG(value) as avg_value,
                MIN(value) as min_value,
                MAX(value) as max_value,
                COUNT(*) as total_readings
            ')
            ->first();
    }
}

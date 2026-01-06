<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'value',
        'raw_data',
        'created_at',
    ];

    protected $casts = [
        'value' => 'float',
        'raw_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the sensor that owns this reading
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Scope to get readings within a time range
     */
    public function scopeWithinTimeRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope to get readings for a specific sensor type
     */
    public function scopeForSensorType($query, string $type)
    {
        return $query->whereHas('sensor', function ($q) use ($type) {
            $q->where('sensor_type', $type);
        });
    }
}

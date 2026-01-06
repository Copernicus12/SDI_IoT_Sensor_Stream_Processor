<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'sensor_reading_id',
        'sensor_type',
        'direction',
        'threshold_value',
        'actual_value',
        'status',
        'notified_channels',
        'resolved_at',
    ];

    protected $casts = [
        'notified_channels' => 'array',
        'resolved_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'sensor_type',
        'direction',
        'value',
        'notify_email',
        'notify_telegram',
        'enabled',
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'notify_telegram' => 'boolean',
        'enabled' => 'boolean',
    ];
}

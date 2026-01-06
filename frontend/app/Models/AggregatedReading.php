<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AggregatedReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'period',
        'bucket_start',
        'avg_value',
        'min_value',
        'max_value',
        'count',
    ];

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}

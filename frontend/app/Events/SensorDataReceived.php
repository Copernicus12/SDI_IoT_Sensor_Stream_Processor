<?php

namespace App\Events;

use App\Models\Sensor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorDataReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sensor;
    public $value;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Sensor $sensor, float $value)
    {
        $this->sensor = [
            'id' => $sensor->id,
            'node_id' => $sensor->node_id,
            'name' => $sensor->name,
            'type' => $sensor->sensor_type,
            'unit' => $sensor->unit,
        ];
        $this->value = $value;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('sensors');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sensor.data';
    }
}

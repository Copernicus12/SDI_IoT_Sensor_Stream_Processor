<?php

namespace App\Console\Commands;

use App\Events\SensorDataReceived;
use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class MqttSubscriberCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe 
                            {--host=127.0.0.1 : MQTT broker host}
                            {--port=1883 : MQTT broker port}
                            {--topic=iot/# : MQTT topic to subscribe to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to MQTT topics and process IoT sensor data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $topic = $this->option('topic');

        $this->info("🚀 Starting MQTT Subscriber...");
        $this->info("📡 Connecting to MQTT broker at {$host}:{$port}");
        $this->info("📌 Subscribing to topic: {$topic}");
        $this->newLine();

        try {
            $mqtt = new MqttClient($host, $port, 'laravel_iot_processor_' . time());
            
            $mqtt->connect();
            $this->info("✅ Connected to MQTT broker successfully!");

            $mqtt->subscribe($topic, function (string $topic, string $message) {
                $this->processMessage($topic, $message);
            }, 0);

            $this->info("👂 Listening for messages... (Press Ctrl+C to stop)");
            $this->newLine();

            $mqtt->loop(true);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Process incoming MQTT message
     */
    private function processMessage(string $topic, string $message): void
    {
        try {
            // Parse the JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("⚠️  Invalid JSON from topic {$topic}: {$message}");
                return;
            }

            // Find the sensor by MQTT topic
            $sensor = Sensor::where('mqtt_topic', $topic)
                ->where('is_active', true)
                ->first();

            if (!$sensor) {
                $this->warn("⚠️  Unknown sensor for topic: {$topic}");
                return;
            }

            // Extract value from message
            $value = $data['value'] ?? null;

            if ($value === null) {
                $this->warn("⚠️  No value in message from {$topic}");
                return;
            }

            // Save the reading
            SensorReading::create([
                'sensor_id' => $sensor->id,
                'value' => $value,
                'raw_data' => $data,
            ]);

            // Display the reading
            $this->line(sprintf(
                "📊 [%s] %s: <fg=green>%s</> %s (Topic: %s)",
                now()->format('H:i:s'),
                $sensor->name,
                number_format($value, 2),
                $sensor->unit,
                $topic
            ));

            // Broadcast to WebSocket for real-time updates
            broadcast(new SensorDataReceived($sensor, $value));

        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
        }
    }
}

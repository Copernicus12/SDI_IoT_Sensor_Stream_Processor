<?php

namespace Database\Seeders;

use App\Models\Sensor;
use Illuminate\Database\Seeder;

class SensorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sensors = [
            // ESP32 Node 1 - DHT11 Sensor (Temperatura + Umiditate)
            [
                'node_id' => 'esp32_node1',
                'sensor_type' => 'temperatura',
                'name' => 'DHT11 - Temperatura',
                'description' => 'Senzor de temperatură DHT11 pe ESP32 Node 1',
                'unit' => '°C',
                'mqtt_topic' => 'iot/esp32_node1/temperatura',
                'is_active' => true,
            ],
            [
                'node_id' => 'esp32_node1',
                'sensor_type' => 'umiditate',
                'name' => 'DHT11 - Umiditate',
                'description' => 'Senzor de umiditate DHT11 pe ESP32 Node 1',
                'unit' => '%',
                'mqtt_topic' => 'iot/esp32_node1/umiditate',
                'is_active' => true,
            ],

            // ESP32 Node 2 - Soil Moisture Sensor
            [
                'node_id' => 'esp32_node2',
                'sensor_type' => 'umiditate_sol',
                'name' => 'Senzor Umiditate Sol',
                'description' => 'Senzor de umiditate a solului pe ESP32 Node 2',
                'unit' => 'ADC',
                'mqtt_topic' => 'iot/esp32_node2/umiditate_sol',
                'is_active' => true,
            ],

            // ESP32 Node 3 - ACS712 Current Sensor
            [
                'node_id' => 'esp32_node3',
                'sensor_type' => 'curent',
                'name' => 'ACS712 - Senzor Curent',
                'description' => 'Senzor de curent ACS712 pe ESP32 Node 3',
                'unit' => 'A',
                'mqtt_topic' => 'iot/esp32_node3/curent',
                'is_active' => true,
            ],
        ];

        foreach ($sensors as $sensor) {
            Sensor::updateOrCreate(
                ['mqtt_topic' => $sensor['mqtt_topic']],
                $sensor
            );
        }

        $this->command->info('✅ Sensors seeded successfully!');
    }
}

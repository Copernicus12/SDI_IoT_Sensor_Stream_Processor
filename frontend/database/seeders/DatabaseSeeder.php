<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SensorThreshold;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Default thresholds by type
        $defaults = [
            ['sensor_type' => 'temperatura', 'direction' => 'above', 'value' => 28.0, 'notify_email' => true, 'notify_telegram' => false],
            ['sensor_type' => 'umiditate_sol', 'direction' => 'below', 'value' => 30.0, 'notify_email' => true, 'notify_telegram' => false],
            ['sensor_type' => 'curent', 'direction' => 'above', 'value' => 2.00, 'notify_email' => true, 'notify_telegram' => false],
        ];
        foreach ($defaults as $d) {
            SensorThreshold::firstOrCreate([
                'sensor_id' => null,
                'sensor_type' => $d['sensor_type'],
                'direction' => $d['direction'],
            ], [
                'value' => $d['value'],
                'notify_email' => $d['notify_email'],
                'notify_telegram' => $d['notify_telegram'],
                'enabled' => true,
            ]);
        }
    }
}

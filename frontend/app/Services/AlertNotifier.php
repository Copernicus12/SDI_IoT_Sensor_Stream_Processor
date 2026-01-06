<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Sensor;
use App\Models\SensorThreshold;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AlertNotifier
{
    public function notify(Alert $alert, Sensor $sensor, SensorThreshold $threshold): void
    {
        $channels = [];
        try {
            if ($threshold->notify_email && ($email = config('services.alerts.email'))) {
                $this->sendEmail($email, $alert, $sensor);
                $channels[] = 'email';
            }
            if ($threshold->notify_telegram && config('services.telegram.bot_token') && config('services.telegram.chat_id')) {
                $this->sendTelegram($alert, $sensor);
                $channels[] = 'telegram';
            }
        } catch (\Throwable $e) {
            Log::error('Alert notify failed: '.$e->getMessage());
        }

        if (!empty($channels)) {
            $alert->update(['notified_channels' => $channels]);
        }
    }

    protected function sendEmail(string $email, Alert $alert, Sensor $sensor): void
    {
        $subject = sprintf('[ALERT] %s %s threshold %s %s (actual: %s %s)',$sensor->name,$sensor->sensor_type,$alert->direction,$alert->threshold_value,$alert->actual_value,$sensor->unit);
        $body = "Sensor: {$sensor->name} ({$sensor->sensor_type})\n".
                "Direction: {$alert->direction}\n".
                "Threshold: {$alert->threshold_value}\n".
                "Actual: {$alert->actual_value} {$sensor->unit}\n".
                "When: {$alert->created_at}\n";
        Mail::raw($body, function ($m) use ($email, $subject) {
            $m->to($email)->subject($subject);
        });
    }

    protected function sendTelegram(Alert $alert, Sensor $sensor): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');
        $text = sprintf("\xE2\x9A\xA0\xEF\xB8\x8F ALERT %s: %s %s %s (actual: %s %s) at %s",
            $sensor->name,
            $sensor->sensor_type,
            $alert->direction,
            $alert->threshold_value,
            $alert->actual_value,
            $sensor->unit,
            $alert->created_at
        );
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = ['chat_id' => $chatId, 'text' => $text];
        try {
            $client = new \GuzzleHttp\Client();
            $client->post($url, ['json' => $payload, 'timeout' => 5]);
        } catch (\Throwable $e) {
            Log::error('Telegram notify failed: '.$e->getMessage());
        }
    }
}

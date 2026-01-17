<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotificationService
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('discord.webhook_url');
    }

    /**
     * Send calendar event notifications
     */
    public function sendEventNotification(
        string $userName,
        string $eventSummary,
        string $startTime,
        ?string $endTime = null
    ): bool {
        $embed = [
            'title' => 'カレンダーに予定が追加されたよ😘',
            'color' => 0x4285F4, // Blue in Google Calendar
            'fields' => [
                [
                    'name' => '追加者',
                    'value' => $userName,
                    'inline' => true,
                ],
                [
                    'name' => '予定',
                    'value' => $eventSummary,
                    'inline' => true,
                ],
                [
                    'name' => '開始時刻',
                    'value' => $startTime,
                    'inline' => true,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        if ($endTime) {
            $embed['fields'][] = [
                'name' => '終了時刻',
                'value' => $endTime,
                'inline' => true,
            ];
        }

        return $this->send(['embeds' => [$embed]]);
    }

    /**
     * Send a message on Discord
     */
    private function send(array $payload): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Discord webhook URL is not configured');
            return false;
        }

        $response = Http::post($this->webhookUrl, $payload);

        if ($response->failed()) {
            Log::error('Failed to send Discord notification', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
    }
}

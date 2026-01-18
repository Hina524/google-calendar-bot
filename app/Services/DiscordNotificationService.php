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
        $timeText = $endTime ? "{$startTime} 〜 {$endTime}" : $startTime;
        $color = $userName === '小西姫奈' ? 0x77DD77 : 0x4285F4;

        $embed = [
            'title' => 'カレンダーに予定が追加されたよ😘',
            'description' => "\u{200b}",
            'color' => $color,
            'fields' => [
                [
                    'name' => '👤 追加者',
                    'value' => $userName . "\n\u{200b}",
                    'inline' => false,
                ],
                [
                    'name' => '📝 予定',
                    'value' => $eventSummary . "\n\u{200b}",
                    'inline' => false,
                ],
                [
                    'name' => '🕐 日時',
                    'value' => $timeText,
                    'inline' => false,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

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

<?php

namespace App\Services;

use App\Models\CalendarUser;
use App\Models\GoogleToken;
use App\Models\WatchChannel;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Channel;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('google.client_id'));
        $this->client->setClientSecret(config('google.client_secret'));
        $this->client->setRedirectUri(config('google.redirect_uri'));
        $this->client->addScope(Calendar::CALENDAR_READONLY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * OAuth認証URLを取得
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * OAuthコールバックを処理してトークンを取得
     */
    public function handleCallback(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * ユーザーのトークンをクライアントに設定
     */
    public function setAccessToken(GoogleToken $token): void
    {
        $this->client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at->diffInSeconds(now()),
        ]);

        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken($token);
        }
    }

    /**
     * トークンを更新
     */
    public function refreshToken(GoogleToken $token): void
    {
        $newToken = $this->client->fetchAccessTokenWithRefreshToken($token->refresh_token);

        $token->update([
            'access_token' => $newToken['access_token'],
            'expires_at' => now()->addSeconds($newToken['expires_in']),
        ]);

        $this->client->setAccessToken($newToken);
    }

    /**
     * Watch Channelを作成（Push通知を受け取るため）
     */
    public function createWatchChannel(CalendarUser $user): WatchChannel
    {
        $this->setAccessToken($user->googleToken);

        $calendarService = new Calendar($this->client);

        $channel = new Channel();
        $channel->setId(Str::uuid()->toString());
        $channel->setType('web_hook');
        $channel->setAddress(config('app.url') . '/api/webhook/calendar');
        $channel->setExpiration((now()->addDays(7)->timestamp * 1000));

        $response = $calendarService->events->watch($user->calendar_id, $channel);

        return WatchChannel::updateOrCreate(
            ['calendar_user_id' => $user->id],
            [
                'channel_id' => $response->getId(),
                'resource_id' => $response->getResourceId(),
                'expiration' => now()->addDays(7),
            ]
        );
    }

    /**
     * Watch Channelを停止
     */
    public function stopWatchChannel(WatchChannel $watchChannel): void
    {
        $this->setAccessToken($watchChannel->calendarUser->googleToken);

        $calendarService = new Calendar($this->client);

        $channel = new Channel();
        $channel->setId($watchChannel->channel_id);
        $channel->setResourceId($watchChannel->resource_id);

        $calendarService->channels->stop($channel);

        $watchChannel->delete();
    }

    /**
     * 最新のイベントを取得
     */
    public function getRecentEvents(CalendarUser $user, ?string $syncToken = null): array
    {
        $this->setAccessToken($user->googleToken);

        $calendarService = new Calendar($this->client);

        $params = [
            'maxResults' => 50,
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ];

        if ($syncToken) {
            $params['syncToken'] = $syncToken;
        } else {
            $params['timeMin'] = now()->toRfc3339String();
            $params['timeMax'] = now()->addMonths(1)->toRfc3339String();
        }

        $events = $calendarService->events->listEvents($user->calendar_id, $params);

        return [
            'events' => $events->getItems(),
            'nextSyncToken' => $events->getNextSyncToken(),
        ];
    }

    /**
     * Google Clientを取得（外部から使う場合）
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}

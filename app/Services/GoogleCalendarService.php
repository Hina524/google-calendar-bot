<?php

namespace App\Services;

use App\Models\CalendarUser;
use App\Models\GoogleToken;
use App\Models\WatchChannel;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Channel;
use Google\Service\Oauth2;
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
        $this->client->addScope(Oauth2::USERINFO_EMAIL);
        $this->client->addScope(Oauth2::USERINFO_PROFILE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback and get access token
     */
    public function handleCallback(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * Set user's access token to the client
     */
    public function setAccessToken(GoogleToken $token): void
    {
        if ($token->expires_at->isPast() && $token->refresh_token) {
            $this->refreshToken($token);
        }

        $this->client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
        ]);
    }

    /**
     * Refresh access token
     */
    public function refreshToken(GoogleToken $token): void
    {
        $newToken = $this->client->fetchAccessTokenWithRefreshToken($token->refresh_token);

        if (isset($newToken['error'])) {
            throw new \Exception('Token refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']));
        }

        $token->update([
            'access_token' => $newToken['access_token'],
            'expires_at' => now()->addSeconds($newToken['expires_in']),
        ]);

        $this->client->setAccessToken($newToken);
    }

    /**
     * Create Watch Channel to receive push notifications
     */
    public function createWatchChannel(CalendarUser $user): WatchChannel
    {
        $this->setAccessToken($user->googleToken);

        $calendarService = new Calendar($this->client);

        $channel = new Channel();
        $channel->setId(Str::uuid()->toString());
        $channel->setType('web_hook');
        $channel->setAddress(config('app.url') . '/api/webhook/calendar');
        $channel->setToken(config('google.webhook_token'));
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
     * Stop Watch Channel
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
     * Get recent events from calendar
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
     * Get Google Client instance
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}

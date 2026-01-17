<?php

namespace App\Http\Controllers;

use App\Models\CalendarUser;
use App\Models\GoogleToken;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    public function __construct(
        private GoogleCalendarService $googleCalendarService
    ) {}

    /**
     * Redirect to Google OAuth authorization page
     */
    public function redirect(): RedirectResponse
    {
        $authUrl = $this->googleCalendarService->getAuthUrl();

        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback from Google
     */
    public function callback(Request $request): string
    {
        $code = $request->query('code');

        if (!$code) {
            return 'Authorization code is missing';
        }

        // Get access token
        $tokenData = $this->googleCalendarService->handleCallback($code);

        if (isset($tokenData['error'])) {
            return 'Error: ' . $tokenData['error_description'];
        }

        // Get user info from Google
        $client = $this->googleCalendarService->getClient();
        $client->setAccessToken($tokenData);

        $oauth2 = new \Google\Service\Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        // Create or update CalendarUser
        $calendarUser = CalendarUser::updateOrCreate(
            ['google_email' => $userInfo->email],
            [
                'name' => $userInfo->name,
                'calendar_id' => 'primary',
                'is_active' => true,
            ]
        );

        // Save tokens
        GoogleToken::updateOrCreate(
            ['calendar_user_id' => $calendarUser->id],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]
        );

        // Create Watch Channel to receive push notifications
        $this->googleCalendarService->createWatchChannel($calendarUser);

        return "認証完了: {$userInfo->name} ({$userInfo->email})";
    }
}

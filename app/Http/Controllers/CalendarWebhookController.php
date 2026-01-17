<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCalendarChange;
use App\Models\WatchChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CalendarWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $channelId = $request->header('X-Goog-Channel-ID');
        $resourceState = $request->header('X-Goog-Resource-State');
        $token = $request->header('X-Goog-Channel-Token');

        Log::info('Calendar webhook received', [
            'channel_id' => $channelId,
            'resource_state' => $resourceState,
        ]);

        // Validate token (reject unauthorized requests)
        if ($token !== config('google.webhook_token')) {
            Log::warning('Invalid webhook token', ['channel_id' => $channelId]);
            return response('Forbidden', 403);
        }

        if ($resourceState === 'sync') {
            return response('OK', 200);
        }

        $watchChannel = WatchChannel::where('channel_id', $channelId)->first();

        if (!$watchChannel) {
            Log::warning('Unknown channel ID', ['channel_id' => $channelId]);
            return response('OK', 200);
        }

        // Dispatch job to process calendar changes
        ProcessCalendarChange::dispatch($channelId);

        return response('OK', 200);
    }
}

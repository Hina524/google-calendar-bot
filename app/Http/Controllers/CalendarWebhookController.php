<?php

namespace App\Http\Controllers;

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

        Log::info('Calendar webhook received', [
            'channel_id' => $channelId,
            'resource_state' => $resourceState,
        ]);

        if ($resourceState === 'sync') {
            return response('OK', 200);
        }

        $watchChannel = WatchChannel::where('channel_id', $channelId)->first();

        if (!$watchChannel) {
            Log::warning('Unknown channel ID', ['channel_id' => $channelId]);
            return response('OK', 200);
        }

        return response('OK', 200);
    }
}

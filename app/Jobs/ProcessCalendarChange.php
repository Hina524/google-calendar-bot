<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Models\WatchChannel;
use App\Services\DiscordNotificationService;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessCalendarChange implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $channelId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        GoogleCalendarService $googleCalendarService,
        DiscordNotificationService $discordService
    ): void {
        $watchChannel = WatchChannel::where('channel_id', $this->channelId)->first();

        if (!$watchChannel) {
            Log::warning('WatchChannel not found', ['channel_id' => $this->channelId]);
            return;
        }

        $user = $watchChannel->calendarUser;

        // Get events from Google Calendar
        $result = $googleCalendarService->getRecentEvents($user, $watchChannel->sync_token);

        // Update sync token for incremental sync
        if ($result['nextSyncToken']) {
            $watchChannel->update(['sync_token' => $result['nextSyncToken']]);
        }

        foreach ($result['events'] as $event) {
            // Skip cancelled events
            if ($event->status === 'cancelled') {
                continue;
            }

            // Check if event already exists (prevent duplicate notifications)
            $exists = CalendarEvent::where('calendar_user_id', $user->id)
                ->where('google_event_id', $event->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // Parse event time
            $startTime = $event->start->dateTime ?? $event->start->date;
            $endTime = $event->end->dateTime ?? $event->end->date;

            // Format time for display
            $formattedStart = $this->formatEventTime($startTime);
            $formattedEnd = $this->formatEventTime($endTime);

            // Save event to database
            CalendarEvent::create([
                'calendar_user_id' => $user->id,
                'google_event_id' => $event->id,
                'summary' => $event->summary ?? '(No title)',
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            // Send Discord notification
            $discordService->sendEventNotification(
                $user->name,
                $event->summary ?? '(No title)',
                $formattedStart,
                $formattedEnd
            );

            Log::info('Event notification sent', [
                'user' => $user->name,
                'event' => $event->summary,
            ]);
        }
    }

    /**
     * Format event time for display
     */
    private function formatEventTime(string $time): string
    {
        // All-day event (date only)
        if (strlen($time) === 10) {
            return date('Y/m/d', strtotime($time));
        }

        // DateTime event
        return date('Y/m/d H:i', strtotime($time));
    }
}

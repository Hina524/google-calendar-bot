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
            // Handle cancelled (deleted) events
            if ($event->status === 'cancelled') {
                $existingEvent = CalendarEvent::where('calendar_user_id', $user->id)
                    ->where('google_event_id', $event->id)
                    ->first();

                if ($existingEvent) {
                    $formattedStart = $this->formatEventTime($existingEvent->start_time);
                    $formattedEnd = $this->formatEventTime($existingEvent->end_time);

                    // Send delete notification
                    $discordService->sendEventDeleteNotification(
                        $user->name,
                        $existingEvent->summary,
                        $formattedStart,
                        $formattedEnd
                    );

                    // Delete from database
                    $existingEvent->delete();

                    Log::info('Event delete notification sent', [
                        'user' => $user->name,
                        'event' => $existingEvent->summary,
                    ]);
                }
                continue;
            }

            // Parse event time
            $startTime = $event->start->dateTime ?? $event->start->date;
            $endTime = $event->end->dateTime ?? $event->end->date;
            $summary = $event->summary ?? '(No title)';

            // Format time for display
            $formattedStart = $this->formatEventTime($startTime);
            $formattedEnd = $this->formatEventTime($endTime);

            // Check if event already exists
            $existingEvent = CalendarEvent::where('calendar_user_id', $user->id)
                ->where('google_event_id', $event->id)
                ->first();

            if ($existingEvent) {
                // Check if event was updated
                if ($existingEvent->summary !== $summary ||
                    $existingEvent->start_time !== $startTime ||
                    $existingEvent->end_time !== $endTime) {

                    // Update event in database
                    $existingEvent->update([
                        'summary' => $summary,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ]);

                    // Send update notification
                    $discordService->sendEventUpdateNotification(
                        $user->name,
                        $summary,
                        $formattedStart,
                        $formattedEnd
                    );

                    Log::info('Event update notification sent', [
                        'user' => $user->name,
                        'event' => $summary,
                    ]);
                }
                continue;
            }

            // Save new event to database
            CalendarEvent::create([
                'calendar_user_id' => $user->id,
                'google_event_id' => $event->id,
                'summary' => $summary,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            // Send Discord notification
            $discordService->sendEventNotification(
                $user->name,
                $summary,
                $formattedStart,
                $formattedEnd
            );

            Log::info('Event notification sent', [
                'user' => $user->name,
                'event' => $summary,
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

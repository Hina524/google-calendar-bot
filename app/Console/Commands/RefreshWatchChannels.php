<?php

namespace App\Console\Commands;

use App\Models\CalendarUser;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class RefreshWatchChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:refresh-watches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Watch Channels that are expiring soon';

    /**
     * Execute the console command.
     */
    public function handle(GoogleCalendarService $googleCalendarService): int
    {
        $users = CalendarUser::where('is_active', true)
            ->whereHas('watchChannel', function ($query) {
                // Refresh channels expiring within 1 day
                $query->where('expiration', '<', now()->addDay());
            })
            ->get();

        if ($users->isEmpty()) {
            $this->info('No Watch Channels need refreshing.');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            try {
                // Stop old channel and create new one
                if ($user->watchChannel) {
                    $googleCalendarService->stopWatchChannel($user->watchChannel);
                }

                $googleCalendarService->createWatchChannel($user);

                $this->info("Refreshed Watch Channel for: {$user->name}");
            } catch (\Exception $e) {
                $this->error("Failed to refresh for {$user->name}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}

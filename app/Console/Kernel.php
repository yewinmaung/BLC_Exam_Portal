<?php

namespace App\Console;

use App\Jobs\InboxSyncJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Automatically sync the IMAP inbox every minute.
        // Reuses the existing InboxSyncJob (which calls InboxSyncService::sync()).
        // withoutOverlapping() prevents a second dispatch if the previous job
        // is still running (e.g. slow IMAP server on the first run).
        $schedule->job(new InboxSyncJob, 'emails')
                 ->everyMinute()
                 ->withoutOverlapping(5)   // lock expires after 5 min
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

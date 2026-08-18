<?php

namespace App\Console;

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
        // Uses the inbox:sync Artisan command which calls InboxSyncService::sync()
        // directly — no queue worker or cache table required.
        // withoutOverlapping() prevents a second run if the previous sync is
        // still executing (slow IMAP connection).
        $schedule->command('inbox:sync')
                 ->everyMinute()
                 ->withoutOverlapping(5);  // lock expires after 5 min

        // Send "Result Available" notifications to students after exam windows close.
        // Runs every minute; withoutOverlapping() prevents stacked runs on slow DB.
        // The command itself is idempotent — duplicate protection is inside the command.
        $schedule->command('results:notify-students')
                 ->everyMinute()
                 ->withoutOverlapping(5);  // lock expires after 5 min

        // Auto-finalize disconnected exam attempts whose recovery window or exam
        // expiry has elapsed while the student did not return to the page.
        // Runs every minute; withoutOverlapping() prevents stacking on slow DB.
        // Uses SessionRecoveryService::finalizeForScheduler() — same finalization
        // path as the web-request handler, grading ALL saved answers.
        $schedule->command('sessions:finalize-expired')
                 ->everyMinute()
                 ->withoutOverlapping(5);  // lock expires after 5 min
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

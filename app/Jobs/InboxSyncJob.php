<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Services\InboxSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * InboxSyncJob
 *
 * Runs InboxSyncService::sync() inside the queue worker so the HTTP
 * request that dispatched it returns immediately.
 *
 * Why this exists
 * ---------------
 * The previous flow called InboxSyncService::sync() directly inside
 * EmailController::syncInbox(), which held the HTTP connection open for
 * the full duration of the IMAP session — easily >60 s on a large mailbox
 * or a slow IMAP server.  Moving the work here means:
 *  - HTTP response returns in <200 ms (just a redirect).
 *  - IMAP timeout is now the queue worker's concern, not PHP-FPM's.
 *  - The existing `emails` queue worker handles this job automatically;
 *    no new workers, no new queues, no config changes required.
 *
 * Queue / retry behaviour
 * -----------------------
 *  - Queue  : 'emails'  (same as SendEmailJob — worker already running)
 *  - Tries  : 2         (one retry is enough for transient IMAP errors)
 *  - Backoff: 60 s      (give the IMAP server time to recover)
 *  - Timeout: 120 s     (generous limit; well above the 60 s imap.php
 *                        account timeout, so the job can finish cleanly)
 *
 * Nothing outside this job is changed:
 *  - InboxSyncService::sync() is called unchanged.
 *  - EmailService, SendEmailJob, OTP jobs — untouched.
 *  - The database queue (jobs table) is used automatically.
 */
class InboxSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Max attempts before the job is moved to failed_jobs. */
    public int $tries = 2;

    /** Seconds between retries. */
    public int $backoff = 60;

    /**
     * Job-level timeout (seconds).
     *
     * PHP's max_execution_time does not apply to CLI workers, so this
     * Horizon / queue-worker timeout takes effect instead.  120 s is
     * enough for 20 messages (the default IMAP_SYNC_LIMIT) even on a
     * slow connection.
     */
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('emails');
    }

    /**
     * How long (seconds) the unique lock should be held.
     * Matches the job timeout so a stuck job never blocks the next scheduled run.
     * Implements ShouldBeUnique — prevents duplicate queued jobs if the scheduler
     * fires before the previous InboxSyncJob has finished.
     */
    public function uniqueFor(): int
    {
        return $this->timeout;
    }

    public function handle(InboxSyncService $syncService): void
    {
        $result = $syncService->sync();

        // Record the activity the same way the controller used to,
        // so the admin activity log still captures sync events.
        try {
            ActivityLog::create([
                'user_id'     => null,   // system / background job
                'action'      => 'inbox_synced',
                'description' => "Inbox sync (queued): {$result['imported']} imported, "
                               . "{$result['skipped']} skipped, {$result['errors']} errors.",
            ]);
        } catch (\Throwable $e) {
            // Activity log failure is non-fatal
            Log::warning('InboxSyncJob: activity log write failed — ' . $e->getMessage());
        }

        if ($result['errors'] > 0) {
            Log::warning('InboxSyncJob completed with errors — ' . $result['message']);
        } else {
            Log::info('InboxSyncJob — ' . $result['message']);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('InboxSyncJob failed after max retries: ' . $e->getMessage());
    }
}

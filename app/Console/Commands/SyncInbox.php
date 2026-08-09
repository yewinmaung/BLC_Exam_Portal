<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\InboxSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SyncInbox
 *
 * Artisan command that calls InboxSyncService::sync() directly — no queue,
 * no cache dependency.  Scheduled every minute in Console\Kernel so new
 * emails appear automatically without clicking "Sync Inbox".
 *
 * The existing manual "Sync Inbox" button (EmailController::syncInbox) is
 * untouched and continues to work independently.
 *
 * Usage:
 *   php artisan inbox:sync          — run once manually
 *   (scheduled)                     — run automatically every minute via cron
 */
class SyncInbox extends Command
{
    protected $signature   = 'inbox:sync';
    protected $description = 'Fetch new emails from IMAP and store them in the inbox';

    public function handle(InboxSyncService $syncService): int
    {
        try {
            $result = $syncService->sync();

            // Write to activity log the same way InboxSyncJob does
            try {
                ActivityLog::create([
                    'user_id'     => null,  // system / scheduled
                    'action'      => 'inbox_synced',
                    'description' => "Inbox sync (scheduled): {$result['imported']} imported, "
                                   . "{$result['skipped']} skipped, {$result['errors']} errors.",
                ]);
            } catch (\Throwable $e) {
                Log::warning('SyncInbox: activity log write failed — ' . $e->getMessage());
            }

            if ($result['errors'] > 0) {
                Log::warning('SyncInbox: ' . $result['message']);
            } else {
                Log::info('SyncInbox: ' . $result['message']);
            }

            $this->line($result['message']);

        } catch (\Throwable $e) {
            Log::error('SyncInbox command failed: ' . $e->getMessage());
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

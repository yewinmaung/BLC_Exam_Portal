<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30; // seconds between retries

    public function __construct(public readonly int $logId)
    {
        $this->onQueue('emails');
    }

    public function handle(EmailService $emailService): void
    {
        $log = EmailLog::find($this->logId);

        if (!$log) {
            return;
        }

        if ($log->status === 'sent') {
            return; // already delivered — idempotent guard
        }

        $emailService->deliver($log);
    }

    public function failed(\Throwable $e): void
    {
        $log = EmailLog::find($this->logId);
        $log?->markFailed('Job failed after max retries: ' . $e->getMessage());
    }
}

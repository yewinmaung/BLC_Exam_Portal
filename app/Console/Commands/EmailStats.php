<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use Illuminate\Console\Command;

class EmailStats extends Command
{
    protected $signature   = 'email:stats';
    protected $description = 'Show email delivery statistics';

    public function handle(): int
    {
        $this->table(
            ['Status', 'Count'],
            [
                ['Sent',   EmailLog::where('status', 'sent')->count()],
                ['Queued', EmailLog::where('status', 'queued')->count()],
                ['Failed', EmailLog::where('status', 'failed')->count()],
                ['Total',  EmailLog::count()],
            ]
        );

        $failed = EmailLog::where('status', 'failed')->latest()->limit(5)->get();
        if ($failed->isNotEmpty()) {
            $this->warn("\nRecent failures:");
            foreach ($failed as $log) {
                $this->line("  #{$log->id} → {$log->to_email} | {$log->error}");
            }
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use Illuminate\Console\Command;

class ProcessScheduledEmails extends Command
{
    protected $signature   = 'email:process-scheduled';
    protected $description = 'Process and dispatch all due scheduled emails';

    public function handle(EmailService $emailService): int
    {
        $count = $emailService->processScheduled();
        $this->info("Processed {$count} scheduled email(s).");
        return Command::SUCCESS;
    }
}

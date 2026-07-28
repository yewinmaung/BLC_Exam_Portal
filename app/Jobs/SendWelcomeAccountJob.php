<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendWelcomeAccountJob
 *
 * Renders emails/welcome-account.blade.php with the user's name, email,
 * role, and temporary password, then queues delivery via the existing
 * EmailService::send() → SendEmailJob → SMTP pipeline.
 *
 * The plaintext temporary password is passed in the constructor and held
 * only in memory for the duration of the job; it is never stored in DB after
 * the job completes (it lives briefly in the jobs table payload while queued).
 *
 * Queue  : emails   (same worker as SendEmailJob — no new infrastructure needed)
 * Tries  : 3
 * Backoff: 30 s
 */
class SendWelcomeAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int    $userId,
        public readonly string $temporaryPassword   // plaintext — for email display only
    ) {
        $this->onQueue('emails');
    }

    public function handle(EmailService $emailService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning("SendWelcomeAccountJob: user #{$this->userId} not found — skipping.");
            return;
        }

        // Determine role label for the email template
        $roleLabel = match (true) {
            $user->isTeacher() => 'teacher',
            $user->isStudent() => 'student',
            default            => 'user',
        };

        // Render the branded welcome-account blade template
        $bodyHtml = view('emails.welcome-account', [
            'userName'          => $user->name,
            'userEmail'         => $user->email,
            'userRole'          => $roleLabel,
            'temporaryPassword' => $this->temporaryPassword,
        ])->render();

        $subject = '[' . config('app.name') . '] Welcome — Your Account is Ready';

        // Queue via the existing EmailService::send() → SendEmailJob → SMTP pipeline
        $emailService->send(
            toEmail:      $user->email,
            toName:       $user->name,
            subject:      $subject,
            bodyHtml:     $bodyHtml,
            event:        'welcome_account',
            templateSlug: null,        // blade-rendered, no DB template
            userId:       $user->id,
            queue:        true,        // uses SendEmailJob on 'emails' queue
            emailType:    'welcome'
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendWelcomeAccountJob failed for user #{$this->userId}: " . $e->getMessage());
    }
}

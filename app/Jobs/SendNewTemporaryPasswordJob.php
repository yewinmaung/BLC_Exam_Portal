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
 * SendNewTemporaryPasswordJob
 *
 * Sends a "Your New Temporary Password" email when a user requests a
 * replacement temporary password after their original one expired.
 *
 * Uses emails/new-temporary-password.blade.php (same visual style as
 * welcome-account.blade.php), subject:
 *   "Your New Temporary Password - Believe Learning Center"
 *
 * Queued on 'emails' queue — same worker as SendEmailJob, no new infra.
 * Plaintext password is in job payload only while queued; never stored in DB.
 */
class SendNewTemporaryPasswordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int    $userId,
        public readonly string $temporaryPassword,   // plaintext — email display only
        public readonly string $expiresAt            // human-readable expiry string
    ) {
        $this->onQueue('emails');
    }

    public function handle(EmailService $emailService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning("SendNewTemporaryPasswordJob: user #{$this->userId} not found — skipping.");
            return;
        }

        $bodyHtml = view('emails.new-temporary-password', [
            'userName'          => $user->name,
            'userEmail'         => $user->email,
            'temporaryPassword' => $this->temporaryPassword,
            'expiresAt'         => $this->expiresAt,
        ])->render();

        $subject = 'Your New Temporary Password - ' . config('app.name', 'Believe Learning Center');

        $emailService->send(
            toEmail:      $user->email,
            toName:       $user->name,
            subject:      $subject,
            bodyHtml:     $bodyHtml,
            event:        'new_temporary_password',
            templateSlug: null,
            userId:       $user->id,
            queue:        true,
            emailType:    'welcome'
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendNewTemporaryPasswordJob failed for user #{$this->userId}: " . $e->getMessage());
    }
}

<?php

namespace App\Jobs;

use App\Mail\ProfileOtpMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Queued job that sends the OTP email for password-change verification.
 *
 * Dispatched to the 'emails' queue (same as SendEmailJob) so the
 * existing queue worker handles it without any configuration change.
 */
class SendProfileOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 15;

    public function __construct(
        public readonly int    $userId,
        public readonly string $code   // plaintext for email display — not stored in DB
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }
        Mail::to($user->email, $user->name)->send(new ProfileOtpMail($user, $this->code));
    }
}

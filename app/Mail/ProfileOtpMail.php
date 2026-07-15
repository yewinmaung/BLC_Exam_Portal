<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email that delivers the 6-digit OTP code for password-change verification.
 * Sent by SendProfileOtpJob.
 */
class ProfileOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $code   // plaintext — displayed in email only
    ) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('[' . config('app.name') . '] Your Password-Change Verification Code')
            ->view('emails.profile-otp')
            ->with([
                'user' => $this->user,
                'code' => $this->code,
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email sent after a successful password change via OTP verification.
 * Dispatched by SendPasswordChangedJob.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('[' . config('app.name') . '] Your Password Has Been Changed')
            ->view('emails.password-changed')
            ->with(['user' => $this->user]);
    }
}

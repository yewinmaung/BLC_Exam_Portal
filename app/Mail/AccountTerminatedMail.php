<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountTerminatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('[' . config('app.name') . '] Your Account Has Been Suspended')
            ->view('emails.account-terminated')
            ->with(['user' => $this->user]);
    }
}

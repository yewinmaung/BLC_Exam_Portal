<?php

namespace App\Mail;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheatingDetectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ExamAttempt $attempt) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('Cheating Alert — Exam Terminated')
            ->view('emails.cheating-detected')
            ->with(['attempt' => $this->attempt]);
    }
}

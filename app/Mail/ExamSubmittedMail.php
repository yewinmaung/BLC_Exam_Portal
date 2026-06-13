<?php

namespace App\Mail;

use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExamSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Exam $exam) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('[' . config('app.name') . '] New Exam Pending Approval: ' . $this->exam->title)
            ->view('emails.exam-submitted')
            ->with(['exam' => $this->exam]);
    }
}

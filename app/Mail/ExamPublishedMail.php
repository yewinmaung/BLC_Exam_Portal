<?php

namespace App\Mail;

use App\Models\Exam;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExamPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Exam $exam) {}

    public function build(): static
    {
        $service  = app(EmailService::class);
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        // Try template-based subject; fall back to default
        $subject = 'New Exam Available: ' . $this->exam->title;
        $tmpl    = \App\Models\EmailTemplate::findBySlug('exam_published');
        if ($tmpl) {
            $rendered = $tmpl->render([
                'exam_name'   => $this->exam->title,
                'course_name' => $this->exam->course->title ?? '',
            ]);
            $subject = $rendered['subject'];
        }

        return $this->from($from, $fromName)
            ->subject($subject)
            ->view('emails.exam-published')
            ->with(['exam' => $this->exam]);
    }
}

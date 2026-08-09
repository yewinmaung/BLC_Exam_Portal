<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Exam Timetable Notification email.
 *
 * Rendered from emails/exam-timetable.blade.php and sent individually
 * per student via the existing SendEmailJob → EmailService::send() pipeline.
 *
 * Data passed:
 *  $studentName          — recipient's full name
 *  $academicYearName     — e.g. "2026-2027"
 *  $yearLevelName        — e.g. "Second Year"
 *  $majorName            — e.g. "Computer Technology" (null if no major)
 *  $semesterLabel        — e.g. "Semester 1"
 *  $exams                — array of exam data rows (see below)
 *  $examPolicy           — optional policy text from admin
 *  $additionalInstructions — optional instructions text from admin
 *
 * Each $exams[] row:
 *  [
 *    'no'            => int,
 *    'subject'       => string,
 *    'exam_date'     => string  e.g. "10 Aug 2026"
 *    'start_time'    => string  e.g. "09:00 AM"
 *    'end_time'      => string  e.g. "11:00 AM"
 *    'allowed_time'  => int     minutes
 *    'attempt_count' => int
 *  ]
 */
class ExamTimetableNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $studentName,
        public readonly string  $academicYearName,
        public readonly string  $yearLevelName,
        public readonly ?string $majorName,
        public readonly string  $semesterLabel,
        public readonly array   $exams,
        public readonly ?string $examPolicy,
        public readonly ?string $additionalInstructions
    ) {}

    public function build(): static
    {
        $from     = config('mail.from.address', 'noreply@believeexam.com');
        $fromName = config('mail.from.name', config('app.name'));

        return $this->from($from, $fromName)
            ->subject('[' . config('app.name') . '] Examination Time Table — ' . $this->semesterLabel)
            ->view('emails.exam-timetable')
            ->with([
                'studentName'            => $this->studentName,
                'academicYearName'       => $this->academicYearName,
                'yearLevelName'          => $this->yearLevelName,
                'majorName'              => $this->majorName,
                'semesterLabel'          => $this->semesterLabel,
                'exams'                  => $this->exams,
                'examPolicy'             => $this->examPolicy,
                'additionalInstructions' => $this->additionalInstructions,
            ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendExamTimetableNotificationJob
 *
 * Renders emails/exam-timetable.blade.php per recipient and queues delivery
 * via the existing EmailService::send() → SendEmailJob → SMTP pipeline.
 *
 * This job itself is dispatched synchronously inside the controller loop
 * (one per student); the actual SMTP delivery is handled by SendEmailJob.
 *
 * Queue : emails  (same worker as SendEmailJob — no new infra)
 * Tries : 3
 * Backoff: 30 s
 */
class SendExamTimetableNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    /**
     * @param int    $userId                  Recipient student user ID
     * @param string $studentName             Recipient display name
     * @param string $studentEmail            Recipient email address
     * @param string $academicYearName        e.g. "2026-2027"
     * @param string $yearLevelName           e.g. "Second Year"
     * @param string|null $majorName          e.g. "Computer Technology" or null
     * @param string $semesterLabel           e.g. "Semester 1"
     * @param array  $exams                   Array of timetable rows
     * @param string|null $examPolicy         Admin-entered policy text
     * @param string|null $additionalInstructions  Admin-entered instructions
     * @param int    $notificationId          FK → exam_timetable_notifications.id
     */
    public function __construct(
        public readonly int     $userId,
        public readonly string  $studentName,
        public readonly string  $studentEmail,
        public readonly string  $academicYearName,
        public readonly string  $yearLevelName,
        public readonly ?string $majorName,
        public readonly string  $semesterLabel,
        public readonly array   $exams,
        public readonly ?string $examPolicy,
        public readonly ?string $additionalInstructions,
        public readonly int     $notificationId
    ) {
        $this->onQueue('emails');
    }

    public function handle(EmailService $emailService): void
    {
        // Render the branded HTML email template
        $bodyHtml = view('emails.exam-timetable', [
            'studentName'            => $this->studentName,
            'academicYearName'       => $this->academicYearName,
            'yearLevelName'          => $this->yearLevelName,
            'majorName'              => $this->majorName,
            'semesterLabel'          => $this->semesterLabel,
            'exams'                  => $this->exams,
            'examPolicy'             => $this->examPolicy,
            'additionalInstructions' => $this->additionalInstructions,
        ])->render();

        $subject = '[' . config('app.name') . '] Examination Time Table — ' . $this->semesterLabel;

        // Queue via the existing EmailService::send() → SendEmailJob → SMTP pipeline
        $emailService->send(
            toEmail:      $this->studentEmail,
            toName:       $this->studentName,
            subject:      $subject,
            bodyHtml:     $bodyHtml,
            event:        'exam_timetable_notification',
            templateSlug: null,           // blade-rendered, no DB template
            userId:       $this->userId,
            queue:        true,           // dispatches SendEmailJob on 'emails' queue
            emailType:    'exam_timetable'
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error(
            "SendExamTimetableNotificationJob failed for user #{$this->userId} "
            . "(notification #{$this->notificationId}): " . $e->getMessage()
        );
    }
}

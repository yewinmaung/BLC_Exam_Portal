<?php

namespace App\Services;

use App\Models\CheatingLog;
use App\Models\ExamAttempt;
use App\Models\User;

/**
 * CheatingDetectionService  — LEGACY / READ-ONLY
 *
 * This service is retained exclusively because the existing admin view at
 * /admin/cheating-logs reads CheatingLog records, and the legacy cheating_logs
 * admin table was built around this service's output.
 *
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  DO NOT call recordViolation() or terminateExam() for new   ║
 * ║  violation reporting.  Use ExamSecurityService instead.     ║
 * ║                                                              ║
 * ║  ExamSessionController no longer injects this service.      ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Responsibilities retained:
 *  - None in the active request path.
 *  - Kept compilable so the DI container does not throw on any existing
 *    binding that has not yet been cleaned up.
 *
 * Safe to remove entirely once the legacy /admin/cheating-logs view is
 * replaced or confirmed to not depend on this class directly.
 *
 * @deprecated  Use ExamSecurityService for all violation handling.
 */

class CheatingDetectionService
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogService $activityLog,
        private GradingService $grading,
        private EmailService $emailService
    ) {
    }

    public function recordViolation(ExamAttempt $attempt, string $type, ?string $details = null): array
    {
        $warningNumber = min($attempt->warning_count + 1, 3);

        CheatingLog::create([
            'attempt_id'     => $attempt->id,
            'student_id'     => $attempt->student_id,
            'violation_type' => $type,
            'details'        => $details,
            'warning_number' => $warningNumber,
        ]);

        $attempt->increment('warning_count');
        $attempt->refresh();

        $this->activityLog->log('cheating_violation', "Violation: {$type}", $attempt);

        if ($attempt->warning_count >= 3) {
            return $this->terminateExam($attempt);
        }

        return [
            'warning_count' => $attempt->warning_count,
            'terminated'    => false,
            'message'       => "Warning {$attempt->warning_count}: Prohibited activity detected.",
        ];
    }

    public function terminateExam(ExamAttempt $attempt): array
    {
        $attempt->update([
            'status'       => 'suspicious',
            'submitted_at' => now(),
        ]);

        $this->grading->gradeAttempt($attempt->fresh(['studentAnswers.answer', 'studentAnswers.question']));

        $exam    = $attempt->exam()->with('teacher')->first();
        $admins  = User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))->get();

        $recipients = collect([$exam->teacher])->merge($admins)->filter();

        $bodyHtml = view('emails.cheating-detected', ['attempt' => $attempt->load('exam', 'student')])->render();

        foreach ($recipients as $recipient) {
            if ($recipient && $recipient->email) {
                try {
                    $this->emailService->send(
                        $recipient->email,
                        $recipient->name,
                        'Cheating Alert — Exam Terminated',
                        $bodyHtml,
                        'cheating_detected',
                        null,
                        $recipient->id,
                        true
                    );
                } catch (\Throwable $e) {
                    logger()->error('CheatingDetectedMail failed: ' . $e->getMessage());
                }
            }

            $this->notifications->notify(
                $recipient,
                'cheating',
                'Cheating Detected',
                "Student {$attempt->student->name} exam terminated due to violations.",
                route('admin.cheating-logs')
            );
        }

        return [
            'warning_count' => 3,
            'terminated'    => true,
            'message'       => 'Exam terminated due to repeated violations.',
            'redirect'      => route('student.exams.index'),
        ];
    }
}

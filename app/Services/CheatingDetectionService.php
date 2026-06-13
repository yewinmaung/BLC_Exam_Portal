<?php

namespace App\Services;

use App\Models\CheatingLog;
use App\Models\ExamAttempt;
use App\Models\User;

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

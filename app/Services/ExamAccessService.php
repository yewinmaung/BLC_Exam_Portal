<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\ReAttemptRequest;
use App\Models\User;
use Carbon\Carbon;

class ExamAccessService
{
    public function __construct(private EncryptionService $encryption)
    {
    }

    public function canDecryptQuestions(User $user, Exam $exam): bool
    {
        if ($user->isAdmin() || ($user->isTeacher() && $exam->teacher_id === $user->id)) {
            return true;
        }

        if (!$user->isStudent()) {
            return false;
        }

        if (!in_array($exam->status, ['approved', 'published'])) {
            return false;
        }

        $schedule = $exam->student_schedule;
        if (!$schedule) {
            return false;
        }

        $now = Carbon::now();

        $inMainWindow = $now->between($schedule->starts_at, $schedule->ends_at);

        $inReattemptWindow = ReAttemptRequest::where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'approved')
            ->whereNotNull('re_attempt_start_at')
            ->whereNotNull('re_attempt_end_at')
            ->where('re_attempt_start_at', '<=', $now)
            ->where('re_attempt_end_at', '>=', $now)
            ->exists();

        return $inMainWindow || $inReattemptWindow;
    }

    public function canViewCorrectAnswers(User $user, Exam $exam): bool
    {
        if ($user->isAdmin() || ($user->isTeacher() && $exam->teacher_id === $user->id)) {
            return true;
        }

        // Students can only see correct answers after the schedule ends
        $schedule = $exam->latestSchedule;
        if (!$schedule) {
            return false;
        }

        return Carbon::now()->gt($schedule->ends_at);
    }

    public function scheduleHasEnded(Exam $exam): bool
    {
        $schedule = $exam->latestSchedule;
        if (!$schedule) {
            return false;
        }
        return Carbon::now()->gt($schedule->ends_at);
    }

    public function isScheduleActive(ExamSchedule $schedule): bool
    {
        $now = Carbon::now();

        return $now->gte($schedule->starts_at) && $now->lte($schedule->ends_at);
    }

    public function isScheduleEnded(ExamSchedule $schedule): bool
    {
        return Carbon::now()->gt($schedule->ends_at);
    }

    public function studentCanTakeExam(User $user, Exam $exam): bool
    {
        if (!$user->isStudent()) {
            return false;
        }

        if (!in_array($exam->status, ['approved', 'published'])) {
            return false;
        }

        $schedule = $exam->student_schedule;
        if (!$schedule) {
            return false;
        }

        if (!$exam->course->enrollments()->where('student_id', $user->id)->exists()) {
            return false;
        }

        // Business rule: default allowed attempts = 1, max attempts = 3
        $baseAllowed = max(1, (int) ($schedule->attempt_limit ?? 1));
        $approvedReattempts = ReAttemptRequest::where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'approved')
            ->count();
        $allowedAttempts = min(3, $baseAllowed + $approvedReattempts);

        $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->whereIn('status', ['submitted', 'terminated', 'suspicious'])
            ->count();

        if ($usedAttempts >= $allowedAttempts) {
            return false;
        }

        // If there is an approved re-attempt window active, allow access even if the main schedule ended/missed.
        $now = Carbon::now();
        $inReattemptWindow = ReAttemptRequest::where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'approved')
            ->whereNotNull('re_attempt_start_at')
            ->whereNotNull('re_attempt_end_at')
            ->where('re_attempt_start_at', '<=', $now)
            ->where('re_attempt_end_at', '>=', $now)
            ->exists();

        if ($inReattemptWindow) {
            return true;
        }

        // Otherwise, student can only take during the main schedule window.
        return $this->isScheduleActive($schedule);
    }

    public function decryptContent(User $user, Exam $exam, ?string $encrypted): ?string
    {
        if (!$this->canDecryptQuestions($user, $exam) && !$this->canViewCorrectAnswers($user, $exam)) {
            return null;
        }

        return $this->encryption->decrypt($encrypted);
    }
}

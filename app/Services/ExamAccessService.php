<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
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

        // CRITICAL: If student has an active attempt, they must be able to see questions
        // even if the schedule window has ended (they started within the window)
        $hasActiveAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->where('status', 'in_progress')
            ->exists();

        if ($hasActiveAttempt) {
            return true;
        }

        $schedule = $exam->student_schedule;
        if (!$schedule) {
            return false;
        }

        $now = Carbon::now();

        return $now->between($schedule->starts_at, $schedule->ends_at);
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

        // Attempt limit is set per schedule; default is 1
        $allowedAttempts = max(1, (int) ($schedule->attempt_limit ?? 1));

        $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
            ->count();

        if ($usedAttempts >= $allowedAttempts) {
            return false;
        }

        // Student can only take during the main schedule window
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

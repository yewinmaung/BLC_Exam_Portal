<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\SessionRecoveryLog;

/**
 * SessionRecoveryService
 *
 * Handles temporary exam session recovery for network disconnections
 * and browser closures.  This is NOT cheating.
 *
 * NEW WORKFLOW (status never changes to terminated_pending_review for a disconnect):
 *
 *   1. Student disconnects → recordDisconnect()
 *      - status stays     : in_progress   (UNCHANGED)
 *      - disconnected_at  : set to now()
 *      - last_question_id : set to current question
 *      - expires_at       : NEVER touched
 *      - student_answers  : NEVER touched
 *
 *   2a. Student returns within 10 min → handleReconnect()
 *      - status stays     : in_progress   (already active, nothing to restore)
 *      - disconnected_at  : cleared (null) after log is written
 *      - timer displayed  : frozen remaining (expires_at − disconnected_at)
 *                           capped by schedule ends_at
 *                           disconnect time is NOT consumed from the exam
 *
 *   2b. Student does NOT return in 10 min → finalizeExpiredSession()
 *      - status set to    : submitted
 *      - submitted_at     : now()
 *      - session token    : cleared
 *      - GradingService   : grades with existing auto-saved answers
 *                           unanswered questions = 0 marks
 *
 *   Anti-cheat path (SEPARATE — untouched by this service):
 *      warning_count reaches 3 → ExamSecurityService sets status = terminated
 *
 * INVARIANTS:
 *   - No new ExamAttempt is ever created.
 *   - expires_at is never modified.
 *   - student_answers is never deleted or modified.
 *   - warning_count is never touched.
 *   - Auto-save logic is never modified.
 *   - No new database tables or columns required.
 */
class SessionRecoveryService
{
    public function __construct(private GradingService $grading) {}

    // ──────────────────────────────────────────────────────────────────────
    //  Step 1 — Record the disconnect
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Record that the student's session was temporarily interrupted.
     *
     * Status STAYS in_progress.
     * Only disconnected_at and last_question_id are written.
     * expires_at and student_answers are never touched.
     *
     * @param  ExamAttempt  $attempt   Must be in_progress
     * @param  int|null     $questionId  Current question the student was viewing
     * @param  string       $reason      e.g. 'browser_close', 'network_error'
     * @param  array        $browserInfo Optional audit metadata
     */
    public function recordDisconnect(
        ExamAttempt $attempt,
        ?int        $questionId,
        string      $reason,
        array       $browserInfo = []
    ): SessionRecoveryLog {
        // Only mark the disconnect timestamp and last question.
        // Status remains in_progress — a temporary disconnect is NOT a termination.
        $attempt->update([
            'disconnected_at'  => now(),
            'last_question_id' => $questionId,
            // status intentionally NOT changed
        ]);

        // Audit log for admin evidence
        return SessionRecoveryLog::create([
            'attempt_id'                    => $attempt->id,
            'student_id'                    => $attempt->student_id,
            'exam_id'                       => $attempt->exam_id,
            'disconnect_reason'             => $reason,
            'disconnected_at'               => now(),
            'last_question_id'              => $questionId,
            'recovery_status'               => 'pending',
            'browser_info'                  => $browserInfo['browser_info'] ?? null,
            'user_agent'                    => $browserInfo['user_agent']   ?? null,
            'ip_address'                    => $browserInfo['ip_address']   ?? null,
            'disconnected_duration_seconds' => null,
            'reconnected_at'                => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Step 2 — Student returns to the exam page
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Evaluate the recovery state when the student returns to /take.
     *
     * Called ONLY when attempt.status === 'in_progress' AND disconnected_at !== null.
     *
     * Path A (within window):
     *   - Clears disconnected_at so the controller knows the session is live.
     *   - Marks the log as recovered.
     *   - Returns success + the frozen remaining seconds for the timer.
     *
     * Path B (window expired or exam time ran out):
     *   - Finalizes the attempt (submitted) using existing workflow.
     *   - Grades with saved answers; unanswered = 0.
     *   - Returns failure + message.
     *
     * @return array{success: bool, message: string, frozen_seconds?: int}
     */
    public function handleReconnect(ExamAttempt $attempt): array
    {
        // ── Check recovery eligibility ────────────────────────────────────
        if (! $attempt->canAutoRecover()) {
            return $this->finalizeExpiredSession(
                $attempt,
                'Your recovery window has expired. '
                . 'Your answers have been saved and your exam has been finalized.'
            );
        }

        // Schedule-end guard (checked here for a specific error message)
        $schedule = $attempt->schedule;
        if ($schedule && now()->gt($schedule->ends_at)) {
            return $this->finalizeExpiredSession(
                $attempt,
                'The exam schedule has ended. '
                . 'Your answers have been saved and your exam has been finalized.'
            );
        }

        // ── Path A: session is restorable ─────────────────────────────────
        // Compute frozen remaining seconds BEFORE clearing disconnected_at
        $frozenSeconds = $this->computeFrozenSeconds($attempt, $schedule);

        $duration = $attempt->disconnected_at->diffInSeconds(now());

        // Clear the disconnect marker — session is live again.
        // Status was already in_progress — nothing to restore.
        $attempt->update(['disconnected_at' => null]);

        // Audit log
        SessionRecoveryLog::where('attempt_id', $attempt->id)
            ->whereNull('reconnected_at')
            ->latest('disconnected_at')
            ->update([
                'recovery_status'               => 'recovered',
                'reconnected_at'                => now(),
                'disconnected_duration_seconds' => $duration,
            ]);

        return [
            'success'        => true,
            'frozen_seconds' => $frozenSeconds,
            'message'        => 'Welcome back! Your session has been restored. '
                              . 'Your answers and remaining time are preserved.',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Timer helper — available to the controller
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Compute how many seconds are left on the exam for a normal (non-recovery) load.
     *
     * Rule: MIN(expires_at − now,  schedule.ends_at − now)
     * Returns 0 if already expired.
     */
    public function computeNormalSeconds(ExamAttempt $attempt, ?object $schedule): int
    {
        $now = now();

        $examRemaining = max(0, (int) $now->diffInSeconds($attempt->expires_at, false));

        if ($schedule) {
            $schedRemaining = max(0, (int) $now->diffInSeconds($schedule->ends_at, false));
            return min($examRemaining, $schedRemaining);
        }

        return $examRemaining;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Compute the frozen remaining exam seconds.
     *
     * Disconnect time is NOT consumed from the student's exam duration:
     *   frozen = expires_at − disconnected_at
     *   final  = MIN(frozen, schedule.ends_at − now)
     */
    private function computeFrozenSeconds(ExamAttempt $attempt, ?object $schedule): int
    {
        // How many seconds were left on the exam clock at the moment of disconnect
        $frozen = max(0, (int) $attempt->disconnected_at->diffInSeconds($attempt->expires_at, false));

        if ($schedule) {
            $schedRemaining = max(0, (int) now()->diffInSeconds($schedule->ends_at, false));
            return min($frozen, $schedRemaining);
        }

        return $frozen;
    }

    /**
     * Finalize an attempt when recovery is no longer possible.
     *
     * Uses the EXISTING submission workflow:
     *  - Sets status = 'submitted'
     *  - Calls GradingService with existing auto-saved answers
     *  - Unanswered questions receive 0 marks (they have no student_answers row)
     *  - Clears the session token
     *  - Marks the recovery log as expired
     *
     * NEVER creates a new attempt, deletes answers, or modifies expires_at.
     *
     * @return array{success: false, message: string}
     */
    private function finalizeExpiredSession(ExamAttempt $attempt, string $message): array
    {
        $duration = $attempt->disconnected_at
            ? $attempt->disconnected_at->diffInSeconds(now())
            : 0;

        // Update audit log
        SessionRecoveryLog::where('attempt_id', $attempt->id)
            ->whereNull('reconnected_at')
            ->latest('disconnected_at')
            ->update([
                'recovery_status'               => 'expired',
                'reconnected_at'                => now(),
                'disconnected_duration_seconds' => $duration,
            ]);

        // Finalize via normal submission workflow
        $attempt->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        // Clear session token — student cannot re-enter
        \App\Models\User::where('id', $attempt->student_id)
            ->update(['exam_session_token' => null]);

        // Grade with auto-saved answers; unanswered questions = 0 marks
        $this->grading->gradeAttempt(
            $attempt->fresh(['studentAnswers.answer', 'studentAnswers.question'])
        );

        return ['success' => false, 'message' => $message];
    }
}

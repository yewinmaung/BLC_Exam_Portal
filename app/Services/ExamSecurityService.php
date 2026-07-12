<?php

namespace App\Services;

use App\Models\CheatingLog;
use App\Models\ExamAttempt;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ExamSecurityService
 *
 * Implements the fixed 3-violation warning sequence (CR: Maximum Warning Behaviour).
 *
 * This service orchestrates existing infrastructure (EmailService,
 * NotificationService, ActivityLogService).  It does NOT replace or modify
 * CheatingDetectionService — the legacy cheating log admin view continues to
 * work exactly as before.
 *
 * Warning 1  (warning_count = 1) — record + warn student only; continue
 * Warning 2  (warning_count = 2) — record + warn + queue email + notification; continue
 * Warning 3  (warning_count = 3) — unconditional termination; attempt = terminated (final)
 *
 * The maximum is fixed at MAX_VIOLATIONS = 3. The auto_terminate_enabled flag
 * no longer gates violation-3 termination. Termination is always unconditional.
 *
 * Approval  → restore in_progress, extend expires_at, write audit (for terminated_pending_review only)
 * Rejection → set rejected, write audit (for terminated_pending_review only)
 *
 * Design notes:
 *  - MAX_VIOLATIONS = 3 is a fixed constant. SecuritySetting::maxWarnings() is no
 *    longer used to determine the termination threshold.
 *  - Emails and notifications are dispatched via DB::afterCommit() so the DB is
 *    always the source of truth before any external side-effect fires.
 *  - Violation-3 is protected against concurrent requests with lockForUpdate().
 *  - Recipients are deduplicated by user ID before any email or notification is sent.
 *  - ActivityLog entries carry structured JSON metadata for queryable audit trails.
 */
class ExamSecurityService
{
    /**
     * Fixed maximum violations before unconditional termination.
     * This constant is not configurable — termination at violation 3 is always enforced.
     */
    private const MAX_VIOLATIONS = 3;

    /**
     * Maximum violations allowed before the exam is terminated.
     * Returns the fixed constant MAX_VIOLATIONS = 3.
     *
     * SecuritySetting::maxWarnings() is retained for display purposes only
     * (e.g. admin UI, notification messages) but no longer gates termination.
     */
    public static function maxWarnings(): int
    {
        return self::MAX_VIOLATIONS;
    }

    /**
     * Cap on how many minutes we extend expires_at during approval.
     * 0 = unlimited.
     */
    public static function maxResumeExtensionMinutes(): int
    {
        return (int) config('exam_security.max_resume_extension_minutes', 120);
    }

    public function __construct(
        private EmailService        $emailService,
        private NotificationService $notifications,
        private ActivityLogService  $activityLog,
        private GradingService      $grading
    ) {}

    // ══════════════════════════════════════════════════════════════════════
    //  Violation recording
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Record a security violation and enforce the fixed 3-violation sequence.
     *
     * Warning 1 (warning_count → 1): warn student, allow continuation.
     * Warning 2 (warning_count → 2): warn student + notify teacher/admins, allow continuation.
     * Warning 3 (warning_count → 3): unconditionally terminate the examination.
     *
     * Duplicate violations of the same type each count independently.
     * Termination is not gated by auto_terminate_enabled — it is always enforced.
     *
     * @param  ExamAttempt  $attempt  A freshly loaded attempt instance.
     * @param  string       $type     Violation type from the JS detector.
     * @param  string|null  $details  Human-readable detail string from JS.
     * @param  array        $client   Optional client metadata (legacy columns on CheatingLog).
     * @param  string       $ip       Request IP address.
     * @return array        JSON-serialisable response for the frontend.
     */
    public function recordViolation(
        ExamAttempt $attempt,
        string      $type,
        ?string     $details,
        array       $client,
        string      $ip
    ): array {
        // All violation types are always enabled — policy DB table removed.
        // To disable a specific violation type, remove its event listener from exam-anticheat.js.

        // Belt-and-braces guard — the controller's isActive() check is the
        // primary gate; this catches any edge-case duplicate POST.
        if ($attempt->warning_count >= self::MAX_VIOLATIONS) {
            return $this->lockedResponse();
        }

        // ── Violation 3 path: unconditional termination with a row lock
        //    to prevent duplicate terminations from concurrent requests.
        if ($attempt->warning_count === (self::MAX_VIOLATIONS - 1)) {
            return $this->recordViolationThree($attempt, $type, $details, $client, $ip);
        }

        // ── Violation 1 / 2 path: simpler — no lock needed for warnings.
        return $this->recordWarning($attempt, $type, $details, $client, $ip);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Internal tier workers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Handle violations 1 and 2 (warning only, or warning + notify).
     * Violation 1: warn student, allow continuation.
     * Violation 2: warn student + notify teacher/admins, allow continuation.
     */
    private function recordWarning(
        ExamAttempt $attempt,
        string      $type,
        ?string     $details,
        array       $client,
        string      $ip
    ): array {
        $newCount = $attempt->warning_count + 1;

        $this->persistViolationLog($attempt, $type, $details, $client, $ip, $newCount);
        $attempt->increment('warning_count');
        $attempt->refresh();

        if ($newCount === 1) {
            return $this->handleWarningOne($attempt, $type);
        }

        // Violation 2: warn + notify.
        return $this->handleWarningTwo($attempt, $type);
    }

    /**
     * Handle violation 3: unconditional termination with a database row lock
     * to prevent duplicate terminations from concurrent requests.
     *
     * Sets attempt.status = 'terminated' (final — no pending review).
     * The student cannot resume. No approval or rejection workflow applies.
     */
    private function recordViolationThree(
        ExamAttempt $attempt,
        string      $type,
        ?string     $details,
        array       $client,
        string      $ip
    ): array {
        $result = null;

        DB::transaction(function () use ($attempt, $type, $details, $client, $ip, &$result) {
            // Re-read with a write lock — concurrent request will block here
            // and find warning_count already >= MAX_VIOLATIONS after the first one commits.
            $locked = ExamAttempt::lockForUpdate()->find($attempt->id);

            if ($locked->warning_count >= self::MAX_VIOLATIONS || $locked->status !== 'in_progress') {
                $result = $this->lockedResponse();
                return;
            }

            $newCount = $locked->warning_count + 1;

            $this->persistViolationLog($locked, $type, $details, $client, $ip, $newCount);

            $locked->update([
                'warning_count' => $newCount,
                'status'        => 'terminated',
                'terminated_at' => now(),
            ]);

            User::where('id', $locked->student_id)->update(['exam_session_token' => null]);

            $gradedAttempt = $locked->fresh(['studentAnswers.answer', 'studentAnswers.question']);
            $this->grading->gradeAttempt($gradedAttempt);
            Result::where('attempt_id', $locked->id)->update([
                'is_published'       => false,
                'is_passed'          => false,
                // Mark as DISQUALIFIED — overrides whatever gradeAttempt set
                'exam_result_status' => Result::STATUS_DISQUALIFIED,
                'violation_reason'   => trim(($details ?? '') !== '' ? ($details ?? '') : str_replace('_', ' ', ucfirst($type))),
                'disqualified_at'    => now(),
                'attendance_status'  => Result::ATTENDANCE_ATTENDED,
                'exam_finished_at'   => now(),
            ]);

            // Build the audit metadata before the after-commit callback captures it.
            $meta = $this->buildAuditMeta($locked, [
                'previous_status' => 'in_progress',
                'new_status'      => 'terminated',
                'violation_type'  => $type,
                'warning_count'   => $newCount,
                'ip'              => $ip,
                'browser'         => $client['browser']  ?? null,
                'device'          => $client['device']   ?? null,
                'os'              => $client['os']        ?? null,
            ]);

            $this->activityLog->log(
                'exam_terminated_security',
                $meta,
                $locked
            );

            // Capture values for after-commit closure (avoid capturing $this
            // or Eloquent models directly to stay serialisation-safe).
            $attemptId = $locked->id;
            $service   = $this;

            DB::afterCommit(function () use ($attemptId, $service) {
                // Re-load a fresh instance after commit for email/notification context.
                $fresh = ExamAttempt::with([
                    'student', 'exam.teacher', 'exam.course', 'cheatingLogs',
                ])->find($attemptId);

                if (! $fresh) {
                    return;
                }

                $recipients = $service->getTerminationRecipients($fresh);

                foreach ($recipients as $recipient) {
                    $service->sendSecurityEmail($fresh, $recipient, 'terminated', true);
                    $service->sendSecurityNotification($fresh, $recipient, true);
                }

                $service->activityLog->log(
                    'security_email_sent',
                    [
                        'attempt_id' => $attemptId,
                        'recipients' => $recipients->pluck('id')->all(),
                        'priority'   => 'high',
                        'template'   => 'security-terminated',
                    ],
                    $fresh
                );
            });

            $reason = trim(($details ?? '') !== '' ? ($details ?? '') : str_replace('_', ' ', ucfirst($type)));

            $result = [
                'warning_count' => $newCount,
                'terminated'    => true,
                'locked'        => true,
                'message'       => 'Your examination has been terminated due to ' . self::MAX_VIOLATIONS . ' security violations. '
                    . 'Reason: ' . $reason . '. '
                    . 'Your result has been invalidated.',
                'redirect'      => route('student.exams.index'),
            ];
        });

        return $result ?? $this->lockedResponse();
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Warning response builders
    // ══════════════════════════════════════════════════════════════════════

    private function handleWarningOne(ExamAttempt $attempt, string $type): array
    {
        $meta = $this->buildAuditMeta($attempt, [
            'previous_status' => 'in_progress',
            'new_status'      => 'in_progress',
            'warning_count'   => $attempt->warning_count,
            'violation_type'  => $type,
        ]);

        $this->activityLog->log(
            'security_warning_1',
            $meta,
            $attempt
        );

        return [
            'warning_count' => 1,
            'terminated'    => false,
            'locked'        => false,
            'message'       => '⚠️ Warning 1 of 3: Prohibited activity detected. '
                . 'A second violation will notify your instructor.',
        ];
    }

    private function handleWarningTwo(ExamAttempt $attempt, string $type): array
    {
        $attempt->load(['student', 'exam.teacher', 'exam.course']);

        $meta = $this->buildAuditMeta($attempt, [
            'previous_status' => 'in_progress',
            'new_status'      => 'in_progress',
            'warning_count'   => $attempt->warning_count,
            'violation_type'  => $type,
        ]);

        $this->activityLog->log(
            'security_warning_2',
            $meta,
            $attempt
        );

        // Violation 2 emails/notifications run after the current HTTP response has
        // written the cheating log — no explicit transaction here because the
        // DB row was updated above.
        $recipients = $this->getRecipients($attempt);

        foreach ($recipients as $recipient) {
            $this->sendSecurityEmail($attempt, $recipient, 'warning', false);
            $this->sendSecurityNotification($attempt, $recipient, false);
        }

        $this->activityLog->log(
            'security_email_sent',
            [
                'attempt_id' => $attempt->id,
                'recipients' => $recipients->pluck('id')->all(),
                'priority'   => 'standard',
                'template'   => 'security-warning',
            ],
            $attempt
        );

        return [
            'warning_count' => $attempt->warning_count,
            'terminated'    => false,
            'locked'        => false,
            'message'       => '🚨 Warning 2 of 3: Your instructor has been notified. '
                . 'Any further violation will immediately terminate your examination.',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Approval
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Approve a terminated_pending_review attempt.
     *
     * Resume policy:
     *  - expires_at is extended by the number of seconds the attempt was locked.
     *  - Extension is capped at config('exam_security.max_resume_extension_minutes').
     *  - If terminated_at is null (shouldn't happen) no extension is applied.
     *  - approved_by / approved_at / approval_comment are set exclusively here.
     *  - rejected_by / rejected_at / rejection_comment remain null.
     *  - terminated_at is cleared (lock is lifted).
     *
     * @throws \Throwable
     */
    public function approve(ExamAttempt $attempt, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($attempt, $actor, $comment) {
            // Locked re-read to prevent race with a concurrent approve/reject.
            $locked = ExamAttempt::lockForUpdate()->find($attempt->id);

            if ($locked->status !== 'terminated_pending_review') {
                return; // Already actioned — idempotent guard.
            }

            // ── Timer extension ───────────────────────────────────────────
            $lockedSeconds  = 0;
            $newExpiresAt   = $locked->expires_at;

            if ($locked->terminated_at) {
                $lockedSeconds = (int) $locked->terminated_at->diffInSeconds(now(), true);

                $maxCap = self::maxResumeExtensionMinutes();
                if ($maxCap > 0) {
                    $lockedSeconds = min($lockedSeconds, $maxCap * 60);
                }

                $newExpiresAt = $locked->expires_at->addSeconds($lockedSeconds);
            }

            $locked->update([
                'status'           => 'in_progress',
                'terminated_at'    => null,
                'expires_at'       => $newExpiresAt,
                'approved_by'      => $actor->id,
                'approved_at'      => now(),
                'approval_comment' => $comment,
                // rejected_* columns intentionally left null
            ]);

            $meta = $this->buildAuditMeta($locked, [
                'previous_status'      => 'terminated_pending_review',
                'new_status'           => 'in_progress',
                'decision'             => 'approved',
                'approved_by'          => $actor->id,
                'approved_by_name'     => $actor->name,
                'warning_count'        => $locked->warning_count,
                'locked_seconds'       => $lockedSeconds,
                'new_expires_at'       => $newExpiresAt->toIso8601String(),
            ]);

            $this->activityLog->log(
                'security_approved',
                $meta,
                $locked
            );

            $attemptId = $locked->id;
            $service   = $this;

            DB::afterCommit(function () use ($attemptId, $actor, $comment, $service) {
                $fresh = ExamAttempt::with(['student', 'exam'])->find($attemptId);
                if (! $fresh) {
                    return;
                }

                $service->notifications->notify(
                    $fresh->student,
                    'security_approved',
                    'Exam Session Approved ✅',
                    'Your exam session has been reviewed and approved. '
                        . 'You may now resume your exam. Your remaining time has been restored.'
                        . ($comment ? " Reviewer note: {$comment}" : ''),
                    route('student.exam.take', $fresh)
                );
            });
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Rejection
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Reject a terminated_pending_review attempt.
     *
     * Rejection policy:
     *  - status → rejected
     *  - terminated_at is intentionally preserved (timestamps when the lock occurred)
     *  - approved_by / approved_at / approval_comment remain null
     *  - rejected_by / rejected_at / rejection_comment are set exclusively here
     *
     * @throws \Throwable
     */
    public function reject(ExamAttempt $attempt, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($attempt, $actor, $comment) {
            $locked = ExamAttempt::lockForUpdate()->find($attempt->id);

            if ($locked->status !== 'terminated_pending_review') {
                return; // Already actioned — idempotent guard.
            }

            $locked->update([
                'status'            => 'rejected',
                'rejected_by'       => $actor->id,
                'rejected_at'       => now(),
                'rejection_comment' => $comment,
                // approved_* columns intentionally left null
                // terminated_at is intentionally preserved
            ]);

            $meta = $this->buildAuditMeta($locked, [
                'previous_status'   => 'terminated_pending_review',
                'new_status'        => 'rejected',
                'decision'          => 'rejected',
                'rejected_by'       => $actor->id,
                'rejected_by_name'  => $actor->name,
                'warning_count'     => $locked->warning_count,
            ]);

            $this->activityLog->log(
                'security_rejected',
                $meta,
                $locked
            );

            $attemptId = $locked->id;
            $service   = $this;

            DB::afterCommit(function () use ($attemptId, $actor, $comment, $service) {
                $fresh = ExamAttempt::with(['student', 'exam'])->find($attemptId);
                if (! $fresh) {
                    return;
                }

                $service->notifications->notify(
                    $fresh->student,
                    'security_rejected',
                    'Exam Session Rejected ❌',
                    'Your exam session was reviewed and rejected due to security violations. '
                        . ($comment ? "Reason: {$comment}" : 'Please contact your instructor for details.'),
                    route('student.exams.show', $fresh->exam_id)
                );
            });
        });
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Helpers — public so tests and after-commit closures can call them
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Persist one CheatingLog row with optional client metadata columns.
     */
    public function persistViolationLog(
        ExamAttempt $attempt,
        string      $type,
        ?string     $details,
        array       $client,
        string      $ip,
        int         $warningNumber
    ): CheatingLog {
        return CheatingLog::create([
            'attempt_id'        => $attempt->id,
            'student_id'        => $attempt->student_id,
            'violation_type'    => $type,
            'details'           => $details,
            'warning_number'    => $warningNumber,
            'user_agent'        => $client['user_agent']        ?? null,
            'browser'           => $client['browser']           ?? null,
            'device'            => $client['device']            ?? null,
            'os'                => $client['os']                ?? null,
            'screen_resolution' => $client['screen_resolution'] ?? null,
            'timezone'          => $client['timezone']          ?? null,
            'ip_address'        => $ip,
        ]);
    }

    /**
     * Collect the responsible teacher + all active admins, deduplicated by ID.
     *
     * Per spec: "Responsible subject teacher only" — not all teachers.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function getRecipients(ExamAttempt $attempt): \Illuminate\Support\Collection
    {
        $teacher = $attempt->exam->teacher;

        $admins = User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                      ->where('is_active', true)
                      ->get();

        // Deduplicate by user ID — a teacher who is also an admin gets one message.
        return collect($teacher ? [$teacher] : [])
            ->merge($admins)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Tier-3 recipients: student, responsible teacher, and active admins.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function getTerminationRecipients(ExamAttempt $attempt): \Illuminate\Support\Collection
    {
        return collect([$attempt->student])
            ->merge($this->getRecipients($attempt))
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Queue a security email to one recipient using the Blade email template.
     *
     * @param  string  $template  'warning' | 'terminated'
     */
    public function sendSecurityEmail(
        ExamAttempt $attempt,
        User        $recipient,
        string      $template,
        bool        $highPriority
    ): void {
        if (! $recipient->email) {
            return;
        }

        $subject = $highPriority
            ? '🚨 [HIGH PRIORITY] Exam Security Incident — Action Required'
            : '⚠️ Exam Security Warning — Student Flagged';

        try {
            $bodyHtml = view("emails.security-{$template}", [
                'attempt'      => $attempt,
                'highPriority' => $highPriority,
            ])->render();

            $this->emailService->send(
                $recipient->email,
                $recipient->name,
                $subject,
                $bodyHtml,
                $highPriority ? 'security_incident_high' : 'security_warning',
                null,
                $recipient->id,
                true   // always queued — never delay the exam
            );
        } catch (\Throwable $e) {
            logger()->error(
                "ExamSecurityService: email failed for recipient #{$recipient->id} — {$e->getMessage()}"
            );
        }
    }

    /**
     * Create one dashboard notification for one recipient.
     *
     * @param  bool  $highPriority  Drives the notification type for badge rendering.
     */
    public function sendSecurityNotification(
        ExamAttempt $attempt,
        User        $recipient,
        bool        $highPriority
    ): void {
        $type    = $highPriority ? 'security_incident_high' : 'security_warning';
        $title   = $highPriority
            ? '🚨 Exam Locked — Security Incident'
            : '⚠️ Security Warning — Student Flagged';
        $message = $highPriority
            ? "Student {$attempt->student->name} has been terminated after "
                . self::MAX_VIOLATIONS . " violations on \"{$attempt->exam->title}\"."
            : "Student {$attempt->student->name} received warning {$attempt->warning_count}/"
                . self::MAX_VIOLATIONS . " on \"{$attempt->exam->title}\".";

        // Route is registered in Phase 6. Gracefully degrade if not yet available.
        $link = \Illuminate\Support\Facades\Route::has('admin.security-incidents.show')
            ? route('admin.security-incidents.show', $attempt)
            : url('/admin/security-incidents/' . $attempt->id);

        $this->notifications->notify($recipient, $type, $title, $message, $link);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Private utilities
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Build a structured metadata array for ActivityLog entries.
     * Stored as JSON in the description field so each key is individually
     * queryable via JSON_EXTRACT / whereJsonContains.
     */
    private function buildAuditMeta(ExamAttempt $attempt, array $extra = []): array
    {
        return array_merge([
            'attempt_id'  => $attempt->id,
            'student_id'  => $attempt->student_id,
            'exam_id'     => $attempt->exam_id,
            'warning_count' => $attempt->warning_count,
        ], $extra);
    }

    /**
     * Standard response when the attempt is already terminated.
     * Handles duplicate POSTs arriving after the third violation is processed.
     */
    private function lockedResponse(): array
    {
        return [
            'warning_count' => self::MAX_VIOLATIONS,
            'terminated'    => true,
            'locked'        => true,
            'message'       => '🔒 Your examination has been terminated.',
            'redirect'      => route('student.exams.index'),
        ];
    }
}

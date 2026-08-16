<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id', 'schedule_id', 'student_id', 'attempt_number', 'status',
        'warning_count', 'started_at', 'submitted_at', 'expires_at', 'session_token',
        // Security extension — Phase 2
        'terminated_at',
        'approved_by', 'approved_at', 'approval_comment',
        'rejected_by',  'rejected_at', 'rejection_comment',
        // Session recovery — Phase 3
        'disconnected_at', 'last_question_id',
        // Question randomization — Phase 4
        'question_order',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'submitted_at'  => 'datetime',
        'expires_at'    => 'datetime',
        // Security extension — Phase 2
        'terminated_at' => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        // Session recovery — Phase 3
        'disconnected_at' => 'datetime',
        // Question randomization — Phase 4
        'question_order'  => 'array',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'schedule_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'attempt_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class, 'attempt_id');
    }

    public function cheatingLogs(): HasMany
    {
        return $this->hasMany(CheatingLog::class, 'attempt_id');
    }

    public function sessionRecoveryLogs(): HasMany
    {
        return $this->hasMany(SessionRecoveryLog::class, 'attempt_id');
    }

    // ── Security extension — Phase 2 ─────────────────────────────────────

    /**
     * The admin or teacher who approved the security incident
     * and restored the attempt to in_progress.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The admin or teacher who rejected the security incident.
     * Distinct from approver — these two paths are mutually exclusive.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // ── Status helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Attempt is locked pending a human review of the security incident.
     * The student cannot continue until an admin or teacher approves.
     */
    public function isTerminatedPendingReview(): bool
    {
        return $this->status === 'terminated_pending_review';
    }

    /**
     * Attempt was rejected after admin review — student cannot continue through normal flow.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Attempt is in a terminal state that prevents further answering.
     * Covers: submitted, terminated, suspicious, terminated_pending_review, rejected.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            'submitted',
            'terminated',
            'suspicious',
            'terminated_pending_review',
            'rejected',
        ]);
    }

    // ── Display helpers ──────────────────────────────────────────────────

    /**
     * Returns true when this attempt should be displayed as "Absent".
     *
     * Conditions (ALL must be true):
     *  1. status is still 'in_progress'   — never formally submitted
     *  2. The exam open window has ended   — student can no longer continue
     *  3. No answers were saved            — student did not meaningfully participate
     *
     * This is a DISPLAY-ONLY helper. It does not change the database status.
     * The active exam flow, session recovery, and auto-submit are unaffected.
     *
     * @param  \App\Models\ExamSchedule|null  $schedule
     */
    public function isDisplayedAsAbsent(?ExamSchedule $schedule): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        // Schedule must exist and its window must have ended
        if (! $schedule || now()->lt($schedule->ends_at)) {
            return false;
        }

        // Student left no answers — they did not participate
        return $this->studentAnswers()->doesntExist();
    }

    /**
     * Returns true when the student's session is in a temporary disconnect
     * state that can still be automatically recovered.
     *
     * Conditions (ALL must be true):
     *  1. status === 'in_progress'  (status never changed during a disconnect)
     *  2. disconnected_at is set    (a disconnect was recorded)
     *  3. Elapsed since disconnect  ≤ recovery_time_limit (default 5 min)
     *  4. expires_at has NOT passed (Final Expiry Time has not been reached)
     *     expires_at = MIN(started_at + duration, schedule.ends_at) — set at attempt creation.
     *     Belt-and-braces: also check schedule.ends_at directly in case an older attempt
     *     was created before the timing fix was deployed.
     */
    public function canAutoRecover(): bool
    {
        if ($this->status !== 'in_progress' || $this->disconnected_at === null) {
            return false;
        }

        $recoveryTimeLimit      = (int) config('exam_security.recovery_time_limit', 300);
        $elapsedSinceDisconnect = (int) $this->disconnected_at->diffInSeconds(now());

        if ($elapsedSinceDisconnect > $recoveryTimeLimit) {
            return false;
        }

        // Primary check: expires_at already encodes MIN(Start+Duration, ends_at)
        // so a single comparison is sufficient for attempts created after the timing fix.
        if (now()->gte($this->expires_at)) {
            return false;
        }

        // Belt-and-braces: also guard against schedule end time directly.
        // Protects legacy attempts whose expires_at was stored without the MIN cap.
        $schedule = $this->schedule;
        if ($schedule && now()->gte($schedule->ends_at)) {
            return false;
        }

        return true;
    }
}

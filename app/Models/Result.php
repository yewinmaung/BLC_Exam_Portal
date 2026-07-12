<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    // ── Status constants ──────────────────────────────────────────────────
    const STATUS_PASSED       = 'PASSED';
    const STATUS_FAILED       = 'FAILED';
    const STATUS_ABSENT       = 'ABSENT';
    const STATUS_DISQUALIFIED = 'DISQUALIFIED';

    const ATTENDANCE_ATTENDED = 'attended';
    const ATTENDANCE_ABSENT   = 'absent';

    protected $fillable = [
        'attempt_id', 'exam_id', 'student_id', 'total_marks', 'obtained_marks',
        'percentage', 'grade', 'is_passed', 'is_published',
        // Phase 5 — Result status extension
        'exam_result_status', 'violation_reason', 'disqualified_at',
        'attendance_status', 'exam_finished_at',
    ];

    protected $casts = [
        'is_passed'       => 'boolean',
        'is_published'    => 'boolean',
        'percentage'      => 'decimal:2',
        'disqualified_at' => 'datetime',
        'exam_finished_at'=> 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────

    public function isPassed(): bool
    {
        return $this->exam_result_status === self::STATUS_PASSED;
    }

    public function isFailed(): bool
    {
        return $this->exam_result_status === self::STATUS_FAILED;
    }

    public function isAbsent(): bool
    {
        return $this->exam_result_status === self::STATUS_ABSENT;
    }

    public function isDisqualified(): bool
    {
        return $this->exam_result_status === self::STATUS_DISQUALIFIED;
    }

    /**
     * Human-readable label for the result status.
     */
    public function statusLabel(): string
    {
        return match ($this->exam_result_status) {
            self::STATUS_PASSED       => 'Passed',
            self::STATUS_FAILED       => 'Failed',
            self::STATUS_ABSENT       => 'Absent',
            self::STATUS_DISQUALIFIED => 'Disqualified',
            default                   => '—',
        };
    }

    /**
     * Bootstrap colour class for the status badge.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->exam_result_status) {
            self::STATUS_PASSED       => 'bg-success',
            self::STATUS_FAILED       => 'bg-danger',
            self::STATUS_ABSENT       => 'bg-secondary',
            self::STATUS_DISQUALIFIED => 'bg-warning text-dark',
            default                   => 'bg-light text-dark',
        };
    }
}

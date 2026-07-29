<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRecoveryLog extends Model
{
    /**
     * Fillable columns must exactly match the session_recovery_logs migration:
     *
     *   disconnected_duration_seconds  — duration the student was offline (int, nullable)
     *   browser_info                   — JSON fingerprint for admin evidence
     *
     * Intentionally excluded:
     *   notes — does not exist in the database; removed to prevent silent write failures.
     */
    protected $fillable = [
        'attempt_id',
        'student_id',
        'exam_id',
        'disconnect_reason',
        'disconnected_at',
        'reconnected_at',
        'disconnected_duration_seconds',
        'last_question_id',
        'ip_address',
        'user_agent',
        'browser_info',
        'recovery_status',
    ];

    protected $casts = [
        'disconnected_at'               => 'datetime',
        'reconnected_at'                => 'datetime',
        'disconnected_duration_seconds' => 'integer',
        'browser_info'                  => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }
}

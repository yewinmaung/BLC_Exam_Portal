<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRecoveryLog extends Model
{
    protected $fillable = [
        'attempt_id',
        'student_id',
        'exam_id',
        'disconnect_reason',
        'disconnected_at',
        'reconnected_at',
        'duration_seconds',
        'last_question_id',
        'ip_address',
        'user_agent',
        'recovery_status',
        'notes',
    ];

    protected $casts = [
        'disconnected_at'  => 'datetime',
        'reconnected_at'   => 'datetime',
        'duration_seconds' => 'integer',
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

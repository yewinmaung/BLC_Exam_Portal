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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }
}

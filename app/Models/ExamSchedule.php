<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSchedule extends Model
{
    protected $fillable = [
        'exam_id', 'starts_at', 'ends_at', 'duration_minutes',
        'attempt_limit', 'target_year', 'is_published', 'published_at', 'published_by',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'is_published'=> 'boolean',
        'published_at'=> 'datetime',
        'target_year' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'schedule_id');
    }
}

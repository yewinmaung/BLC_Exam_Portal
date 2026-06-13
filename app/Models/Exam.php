<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id', 'teacher_id', 'title', 'description', 'status',
        'total_marks', 'passing_marks', 'shuffle_questions',
        'submitted_at', 'approved_at', 'approved_by',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    /**
     * Published schedule (used for "published" status exams).
     */
    public function activeSchedule(): HasOne
    {
        return $this->hasOne(ExamSchedule::class)
            ->where('is_published', true)
            ->latestOfMany();
    }

    /**
     * Any schedule (published or not) — used for "approved" status exams
     * so students can take the exam as soon as admin sets a schedule.
     */
    public function anySchedule(): HasOne
    {
        return $this->hasOne(ExamSchedule::class)->latestOfMany();
    }

    public function latestSchedule(): HasOne
    {
        return $this->hasOne(ExamSchedule::class)->latestOfMany();
    }

    /**
     * Returns the best available schedule for student access:
     * - published schedule if exam is published
     * - any schedule if exam is approved
     */
    public function getStudentScheduleAttribute(): ?ExamSchedule
    {
        if ($this->status === 'published') {
            return $this->activeSchedule;
        }
        if ($this->status === 'approved') {
            return $this->latestSchedule;
        }
        return null;
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'attempt_id', 'exam_id', 'student_id', 'total_marks', 'obtained_marks',
        'percentage', 'grade', 'is_passed', 'is_published',
    ];

    protected $casts = [
        'is_passed' => 'boolean',
        'is_published' => 'boolean',
        'percentage' => 'decimal:2',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = [
        'course_id',
        'student_id',
        'enrolled_at',
        'year',          // legacy integer 1–5 (kept for backward compatibility)
        'year_level_id', // FK → year_levels (proper relational column)
        'major_id',      // FK → majors (null for Year 1 students)
    ];

    protected $casts = [
        'enrolled_at'  => 'datetime',
        'year'         => 'integer',
        'year_level_id' => 'integer',
        'major_id'      => 'integer',
    ];

    public static function yearOptions(): array
    {
        return \App\Support\AcademicYear::OPTIONS;
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }
}

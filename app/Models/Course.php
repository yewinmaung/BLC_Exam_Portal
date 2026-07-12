<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'code', 'description',
        'teacher_id', 'created_by',
        'is_active',
        'year_level',       // integer 0–5 (0 = all years, legacy wildcard)
        'academic_year_id',
        'semester',         // integer 0–2 (0 = both, 1 = sem1, 2 = sem2)
        'major_id',         // FK → majors
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'year_level'       => 'integer',
        'semester'         => 'integer',
        'academic_year_id' => 'integer',
        'major_id'         => 'integer',
    ];

    // ── Static Label Maps ────────────────────────────────────────────────

    public static array $yearLevelLabels = [
        0 => 'All Year Levels',
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
        5 => 'Fifth Year',
    ];

    public static array $semesterLabels = [
        0 => 'Both Semesters',
        1 => 'Semester 1',
        2 => 'Semester 2',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function yearLevel(): BelongsTo
    {
        // Convenience relationship via the integer `year_level` mapped to year_levels.level.
        // Note: the FK is the integer level value, not an ID — use whereRaw for matching.
        return $this->belongsTo(YearLevel::class, 'year_level', 'level');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_id', 'student_id')
            ->withTimestamps();
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Returns true if this course requires a specific major (year 2+).
     */
    public function requiresMajor(): bool
    {
        return $this->year_level >= 2 && $this->year_level !== 0;
    }

    /**
     * Human-readable year level label.
     */
    public function getYearLevelLabelAttribute(): string
    {
        return static::$yearLevelLabels[$this->year_level] ?? 'Year ' . $this->year_level;
    }

    /**
     * Human-readable semester label.
     */
    public function getSemesterLabelAttribute(): string
    {
        return static::$semesterLabels[$this->semester] ?? 'Semester ' . $this->semester;
    }
}

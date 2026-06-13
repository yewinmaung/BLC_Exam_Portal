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
        'title', 'code', 'description', 'teacher_id', 'created_by',
        'is_active', 'year_level', 'academic_year_id', 'semester',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'year_level'       => 'integer',
        'semester'         => 'integer',
        'academic_year_id' => 'integer',
    ];

    public static array $yearLevelLabels = [
        0 => 'All Year Levels',
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
        5 => 'Final Year',
    ];

    public static array $semesterLabels = [
        0 => 'Both Semesters',
        1 => 'Semester 1',
        2 => 'Semester 2',
    ];

    public function academicYear(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicYear::class);
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
}

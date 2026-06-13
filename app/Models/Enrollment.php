<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = ['course_id', 'student_id', 'enrolled_at', 'year'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'year'        => 'integer',
    ];

    public static function yearOptions(): array
    {
        return \App\Support\AcademicYear::OPTIONS;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

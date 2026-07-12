<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentYearRecord extends Model
{
    protected $fillable = [
        'student_id', 'academic_year_id', 'year_level_id',
        'semester', 'department', 'major', 'gpa', 'status', 'promoted_at',
    ];
    protected $casts = ['promoted_at' => 'datetime', 'gpa' => 'decimal:2'];

    public function student(): BelongsTo      { return $this->belongsTo(User::class, 'student_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function yearLevel(): BelongsTo    { return $this->belongsTo(YearLevel::class); }
}

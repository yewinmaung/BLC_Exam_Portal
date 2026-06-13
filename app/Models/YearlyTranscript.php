<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearlyTranscript extends Model
{
    protected $fillable = [
        'student_id', 'academic_year_id', 'year_level_id', 'semester',
        'gpa', 'total_marks', 'obtained_marks', 'percentage',
        'grade', 'is_passed', 'status', 'generated_by',
    ];

    protected $casts = [
        'is_passed'      => 'boolean',
        'gpa'            => 'decimal:2',
        'percentage'     => 'decimal:2',
        'total_marks'    => 'decimal:2',
        'obtained_marks' => 'decimal:2',
    ];

    public function student(): BelongsTo      { return $this->belongsTo(User::class, 'student_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function yearLevel(): BelongsTo    { return $this->belongsTo(YearLevel::class); }
    public function generatedBy(): BelongsTo  { return $this->belongsTo(User::class, 'generated_by'); }
}

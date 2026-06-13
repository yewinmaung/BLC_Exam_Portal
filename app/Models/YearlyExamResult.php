<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearlyExamResult extends Model
{
    protected $fillable = [
        'student_id', 'academic_year_id', 'year_level_id', 'exam_id',
        'result_id', 'obtained_marks', 'total_marks', 'percentage', 'grade', 'is_passed', 'semester',
    ];
    protected $casts = ['is_passed' => 'boolean', 'percentage' => 'decimal:2'];

    public function student(): BelongsTo      { return $this->belongsTo(User::class, 'student_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function yearLevel(): BelongsTo    { return $this->belongsTo(YearLevel::class); }
    public function exam(): BelongsTo         { return $this->belongsTo(Exam::class); }
    public function result(): BelongsTo       { return $this->belongsTo(Result::class); }
}

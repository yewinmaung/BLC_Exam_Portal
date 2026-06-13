<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionHistory extends Model
{
    protected $fillable = [
        'student_id', 'from_year_level_id', 'to_year_level_id',
        'academic_year_id', 'promoted_by', 'notes', 'promoted_at',
    ];
    protected $casts = ['promoted_at' => 'datetime'];

    public function student(): BelongsTo      { return $this->belongsTo(User::class, 'student_id'); }
    public function fromYearLevel(): BelongsTo{ return $this->belongsTo(YearLevel::class, 'from_year_level_id'); }
    public function toYearLevel(): BelongsTo  { return $this->belongsTo(YearLevel::class, 'to_year_level_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function promotedBy(): BelongsTo   { return $this->belongsTo(User::class, 'promoted_by'); }
}

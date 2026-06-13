<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateLog extends Model
{
    protected $fillable = [
        'serial_number', 'student_id', 'academic_year_id', 'year_level_id',
        'type', 'issued_by', 'qr_token', 'issued_at', 'created_by',
    ];
    protected $casts = ['issued_at' => 'datetime'];

    public static array $types = [
        'transcript'  => 'Result Transcript',
        'completion'  => 'Completion Certificate',
        'promotion'   => 'Promotion Certificate',
        'achievement' => 'Academic Achievement Certificate',
    ];

    public function student(): BelongsTo      { return $this->belongsTo(User::class, 'student_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function yearLevel(): BelongsTo    { return $this->belongsTo(YearLevel::class); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
}

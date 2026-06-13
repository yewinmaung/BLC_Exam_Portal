<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamImportLog extends Model
{
    protected $fillable = [
        'exam_id', 'imported_by', 'original_filename', 'file_type',
        'questions_found', 'questions_imported', 'parse_log', 'status',
    ];

    public function exam(): BelongsTo       { return $this->belongsTo(Exam::class); }
    public function importer(): BelongsTo   { return $this->belongsTo(User::class, 'imported_by'); }
}

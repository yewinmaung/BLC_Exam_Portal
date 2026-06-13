<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReAttemptRequest extends Model
{
    protected $table = 're_attempt_requests';

    protected $fillable = [
        'student_id', 'teacher_id', 'exam_id',
        'reason', 'status', 'admin_remark',
        'approved_by', 'approved_at', 'sent_to_admin_at',
        're_attempt_start_at', 're_attempt_end_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'sent_to_admin_at' => 'datetime',
        're_attempt_start_at' => 'datetime',
        're_attempt_end_at' => 'datetime',
    ];

    public static array $reasons = [
        'technical_issue' => 'Technical Issue',
        'medical_issue'   => 'Medical Issue',
        'approved_absence'=> 'Approved Absence',
        'system_error'    => 'System Error',
        'academic_review' => 'Academic Review',
        'other'           => 'Other',
    ];

    public function student(): BelongsTo  { return $this->belongsTo(User::class, 'student_id'); }
    public function teacher(): BelongsTo  { return $this->belongsTo(User::class, 'teacher_id'); }
    public function exam(): BelongsTo     { return $this->belongsTo(Exam::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function logs(): HasMany       { return $this->hasMany(ReAttemptLog::class, 'request_id'); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExamTimetableNotification
 *
 * Stores the batch-level log for each "Exam Time Table Notification"
 * sent manually by admin from the Email Compose tab.
 *
 * Per-recipient delivery is tracked separately in email_logs
 * (email_type = 'exam_timetable').
 */
class ExamTimetableNotification extends Model
{
    protected $fillable = [
        'sent_by',
        'academic_year_id',
        'year_level_id',
        'major_id',
        'semester',
        'exam_schedule_ids',
        'exam_policy',
        'additional_instructions',
        'recipient_count',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'exam_schedule_ids' => 'array',
        'sent_at'           => 'datetime',
        'semester'          => 'integer',
        'recipient_count'   => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Human-readable semester label.
     */
    public function getSemesterLabelAttribute(): string
    {
        return 'Semester ' . $this->semester;
    }

    /**
     * Short description of recipient group for display.
     */
    public function getGroupSummaryAttribute(): string
    {
        $parts = [
            $this->academicYear?->name ?? '—',
            $this->yearLevel?->name    ?? '—',
        ];

        if ($this->major) {
            $parts[] = $this->major->name;
        }

        $parts[] = 'Semester ' . $this->semester;

        return implode(' · ', $parts);
    }
}

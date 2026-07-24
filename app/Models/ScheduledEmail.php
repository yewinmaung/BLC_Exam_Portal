<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Academic Notification Scheduler entry.
 *
 * Each record defines:
 *  - notification_type     : exam_time | exam_policy | exam_reminder
 *  - exam_ids              : which exams to include in the notification
 *  - filter_academic_years : limit to students in these academic years  ([] = all)
 *  - filter_year_levels    : limit to students in these year levels     ([] = all)
 *  - filter_majors         : limit to students in these majors          ([] = all)
 *
 * Recipients are resolved dynamically at send time from StudentYearRecord.
 * Email content is rendered from resources/views/emails/academic-notification.blade.php.
 */
class ScheduledEmail extends Model
{
    protected $fillable = [
        'name',
        'notification_type',
        'filter_academic_years',
        'filter_year_levels',
        'filter_majors',
        'exam_ids',
        'send_at',
        'is_sent',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'send_at'               => 'datetime',
        'sent_at'               => 'datetime',
        'is_sent'               => 'boolean',
        'filter_academic_years' => 'array',
        'filter_year_levels'    => 'array',
        'filter_majors'         => 'array',
        'exam_ids'              => 'array',
    ];

    // ── Notification type labels ─────────────────────────────────────────

    public static array $notificationTypes = [
        'exam_time'     => 'Exam Time',
        'exam_policy'   => 'Exam Policy',
        'exam_reminder' => 'Exam Reminder',
    ];

    public static array $notificationDescriptions = [
        'exam_time'     => 'Notifies students of their exam date, time, room, and duration.',
        'exam_policy'   => 'Sends the exam rules and instructions to enrolled students.',
        'exam_reminder' => 'Sends a reminder message before the exam.',
    ];

    /**
     * Recipient group labels — kept for backward compatibility with the
     * Bulk Email and Compose features that still use group-based targeting.
     */
    public static array $recipientLabels = [
        'all_students'   => 'All Students',
        'first_year'     => 'First Year Students',
        'second_year'    => 'Second Year Students',
        'third_year'     => 'Third Year Students',
        'fourth_year'    => 'Fourth Year Students',
        'final_year'     => 'Final Year Students',
        'all_teachers'   => 'All Teachers',
        'all_users'      => 'All Users',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function getNotificationTypeLabelAttribute(): string
    {
        return static::$notificationTypes[$this->notification_type] ?? $this->notification_type;
    }

    /**
     * Returns a human-readable summary of the applied filters for display.
     */
    public function getFilterSummaryAttribute(): string
    {
        $parts = [];

        if (!empty($this->filter_academic_years)) {
            $count = count($this->filter_academic_years);
            $parts[] = $count . ' academic year' . ($count > 1 ? 's' : '');
        }

        if (!empty($this->filter_year_levels)) {
            $count = count($this->filter_year_levels);
            $parts[] = $count . ' year level' . ($count > 1 ? 's' : '');
        }

        if (!empty($this->filter_majors)) {
            $count = count($this->filter_majors);
            $parts[] = $count . ' major' . ($count > 1 ? 's' : '');
        }

        return empty($parts) ? 'All students' : implode(', ', $parts);
    }
}

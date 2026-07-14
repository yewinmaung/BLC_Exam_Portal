<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'link', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Category mapping
    //
    //  Maps notification `type` values to a nav category so badges can be
    //  shown on the relevant sidebar item.
    //
    //  Rules:
    //   - Do NOT create new tables or columns.
    //   - The `type` field already in user_notifications is the sole signal.
    //   - Any unmapped type falls into the 'general' bucket (shown on the
    //     global Notifications bell, not on a specific nav item).
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Maps every known notification type to a nav category.
     *
     * Categories used in the student sidebar:
     *   'exam'    → Exams nav item and sub-items
     *   'result'  → My Results sub-item
     *   'course'  → My Courses nav item
     *   'general' → Global Notifications bell (fallback)
     */
    public const CATEGORY_MAP = [
        // ── Exam lifecycle (admin/teacher actions) ─────────────────────
        'exam_approved'              => 'exam',
        'exam_published'             => 'exam',
        'exam_submitted'             => 'exam',
        'exam_schedule'              => 'exam',
        'exam_schedule_created'      => 'exam',
        'exam_soon'                  => 'exam',
        'exam_started'               => 'exam',
        'exam_closed'                => 'exam',
        'question_added'             => 'exam',   // teacher adds question to pending exam

        // ── Security / anti-cheat ──────────────────────────────────────
        'cheating'                   => 'exam',
        'security_warning'           => 'exam',
        'security_incident_high'     => 'exam',
        'security_approved'          => 'exam',
        'security_rejected'          => 'exam',
        'exam_terminated'            => 'exam',
        'exam_recovery'              => 'exam',

        // ── Results ───────────────────────────────────────────────────
        'exam_result'                => 'result',
        'result_published'           => 'result',
        'exam_graded'                => 'result',
        'exam_result_released'       => 'result',

        // ── Courses / enrollment ──────────────────────────────────────
        'enrolled'                   => 'course',
        'enrollment_removed'         => 'course',
        'course_updated'             => 'course',
        'course_published'           => 'course',
    ];

    /**
     * Return the nav category for a given notification type.
     * Falls back to 'general' for any unknown type.
     */
    public static function categoryFor(string $type): string
    {
        return static::CATEGORY_MAP[$type] ?? 'general';
    }

    /**
     * Return the nav category for this notification instance.
     */
    public function getCategory(): string
    {
        return static::categoryFor($this->type);
    }

    /**
     * Scope: filter notifications belonging to a specific nav category.
     *
     * Usage:
     *   UserNotification::forCategory('exam')->where('user_id', $id)->count()
     */
    public function scopeForCategory($query, string $category)
    {
        $types = array_keys(array_filter(
            static::CATEGORY_MAP,
            fn ($cat) => $cat === $category
        ));

        return $query->whereIn('type', $types);
    }

    /**
     * Mark all unread notifications of a given category as read for a user.
     *
     * Called automatically when the user visits the related nav page.
     * Never called at notification creation time.
     * Never deletes records — only sets is_read = true.
     *
     * @param  int     $userId
     * @param  string  $category  One of: 'exam', 'result', 'course', 'general'
     */
    public static function markCategoryRead(int $userId, string $category): void
    {
        $types = array_keys(array_filter(
            static::CATEGORY_MAP,
            fn ($cat) => $cat === $category
        ));

        if (empty($types) && $category !== 'general') {
            return;
        }

        $query = static::where('user_id', $userId)->where('is_read', false);

        if ($category === 'general') {
            // General = any type NOT in CATEGORY_MAP
            $allMappedTypes = array_keys(static::CATEGORY_MAP);
            $query->whereNotIn('type', $allMappedTypes);
        } else {
            $query->whereIn('type', $types);
        }

        $query->update(['is_read' => true]);
    }

    /**
     * Count unread notifications per category for a given user.
     *
     * Returns an array keyed by category with unread counts.
     * Categories with 0 unread are still included (value = 0).
     *
     * @param  int  $userId
     * @return array{exam: int, result: int, course: int, general: int}
     */
    public static function unreadCountsByCategory(int $userId): array
    {
        $counts = [
            'exam'    => 0,
            'result'  => 0,
            'course'  => 0,
            'general' => 0,
        ];

        // Single query — group by type, filter to unread
        $rows = static::where('user_id', $userId)
            ->where('is_read', false)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        foreach ($rows as $type => $cnt) {
            $category = static::categoryFor($type);
            if (array_key_exists($category, $counts)) {
                $counts[$category] += $cnt;
            } else {
                $counts['general'] += $cnt;
            }
        }

        return $counts;
    }
}

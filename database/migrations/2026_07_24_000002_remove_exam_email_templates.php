<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the exam_submitted and exam_published email templates from the database.
 *
 * These templates are no longer used:
 *  - exam_submitted  → admin notification on teacher exam submission
 *  - exam_published  → student notification on exam publish
 *
 * Both flows now use in-app notifications only (NotificationService).
 * The corresponding Mail classes and Blade views have been deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->whereIn('slug', ['exam_submitted', 'exam_published'])
            ->delete();
    }

    public function down(): void
    {
        // Re-insert minimal stub records so rollback doesn't break template lookups.
        $now = now();

        DB::table('email_templates')->insertOrIgnore([
            [
                'name'       => 'Exam Submitted for Approval',
                'slug'       => 'exam_submitted',
                'subject'    => '[{{app_name}}] New Exam Pending Approval: {{exam_name}}',
                'body_html'  => '<p>{{teacher_name}} submitted {{exam_name}} for review.</p>',
                'event'      => 'exam_submitted',
                'is_active'  => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Exam Published Notification',
                'slug'       => 'exam_published',
                'subject'    => '[{{app_name}}] New Exam Available: {{exam_name}}',
                'body_html'  => '<p>{{student_name}}, {{exam_name}} is now available.</p>',
                'event'      => 'exam_published',
                'is_active'  => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redesign scheduled_emails into an Academic Notification Scheduler.
 *
 * Replaces manual subject/body/recipients/template_slug fields with:
 *  - notification_type  : exam_time | exam_policy | exam_reminder
 *  - exam_ids           : JSON array of exam IDs to notify about
 *  - filter_academic_years : JSON array of academic_year IDs (empty = all)
 *  - filter_year_levels    : JSON array of year_level IDs (empty = all)
 *  - filter_majors         : JSON array of major IDs (empty = all)
 *
 * Recipients are resolved dynamically at send time from StudentYearRecord.
 * Email content is generated from the fixed academic-notification blade template.
 *
 * Legacy columns (subject, body_html, recipients, template_slug) are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_emails', function (Blueprint $table) {
            // ── Drop legacy free-form columns ──────────────────────────
            $table->dropColumn(['subject', 'body_html', 'recipients', 'template_slug']);

            // ── New academic notification columns ──────────────────────
            $table->enum('notification_type', ['exam_time', 'exam_policy', 'exam_reminder'])
                  ->after('name')
                  ->default('exam_reminder');

            // JSON arrays of filter IDs — empty array means "all"
            $table->json('filter_academic_years')->after('notification_type')->default('[]');
            $table->json('filter_year_levels')->after('filter_academic_years')->default('[]');
            $table->json('filter_majors')->after('filter_year_levels')->default('[]');
            $table->json('exam_ids')->after('filter_majors')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_emails', function (Blueprint $table) {
            $table->dropColumn([
                'notification_type',
                'filter_academic_years',
                'filter_year_levels',
                'filter_majors',
                'exam_ids',
            ]);

            // Restore legacy columns
            $table->string('template_slug')->nullable()->after('name');
            $table->string('subject')->after('template_slug');
            $table->longText('body_html')->after('subject');
            $table->string('recipients')->after('body_html');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Schedule Mail System and Template Base Email System tables.
 *
 * Both tables are no longer used by any remaining feature:
 *  - email_templates: was used by EmailTemplate model / Template CRUD (removed)
 *  - scheduled_emails: was used by ScheduledEmail model / Academic Notification Scheduler (removed)
 *
 * Preserved tables (untouched):
 *  - email_logs        : used by all outgoing email flows
 *  - email_campaigns   : referenced by email_logs FK (campaign_id)
 *  - inbox_emails      : inbox / IMAP sync feature
 *  - exam_timetable_notifications : timetable notification logs
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop scheduled_emails first (no FK dependencies pointing to it)
        Schema::dropIfExists('scheduled_emails');

        // Drop email_templates (email_logs.template_slug is a plain string — no FK constraint)
        Schema::dropIfExists('email_templates');
    }

    public function down(): void
    {
        // Restore email_templates
        Schema::create('email_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->string('event')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Restore scheduled_emails (academic notification format)
        Schema::create('scheduled_emails', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('notification_type', ['exam_time', 'exam_policy', 'exam_reminder'])
                  ->default('exam_reminder');
            $table->json('filter_academic_years')->default('[]');
            $table->json('filter_year_levels')->default('[]');
            $table->json('filter_majors')->default('[]');
            $table->json('exam_ids')->default('[]');
            $table->timestamp('send_at');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};

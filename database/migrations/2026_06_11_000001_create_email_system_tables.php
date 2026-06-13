<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Email Templates ──────────────────────────────────────────
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // Internal name e.g. "exam_published"
            $table->string('slug')->unique();          // Machine key
            $table->string('subject');
            $table->longText('body_html');             // HTML body with {{variables}}
            $table->text('body_text')->nullable();     // Plain-text fallback
            $table->string('event')->nullable();       // auto-trigger event slug
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Email Logs ───────────────────────────────────────────────
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->string('template_slug')->nullable();
            $table->string('event')->nullable();           // which event triggered it
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->string('provider')->default('smtp'); // smtp / log / array
            $table->text('error')->nullable();
            $table->string('message_id')->nullable();      // SMTP message-id header
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('to_email');
        });

        // ── Scheduled Emails ─────────────────────────────────────────
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_slug')->nullable();
            $table->string('subject');
            $table->longText('body_html');
            $table->string('recipients');         // all_students|first_year|teachers|etc.
            $table->timestamp('send_at');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_emails');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
    }
};

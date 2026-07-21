<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inbox_emails — Admin email inbox for Phase 1.
 *
 * Phase 1: manual/test records only (no IMAP/webhook yet).
 * Phase 2 will add IMAP polling; the schema is designed to support it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_emails', function (Blueprint $table) {
            $table->id();

            // ── Sender info ──────────────────────────────────────────────
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->enum('sender_type', ['student', 'external'])->default('external');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Linked user if sender_type = student and email matches a user');

            // ── Email content ────────────────────────────────────────────
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();

            // ── Threading ────────────────────────────────────────────────
            $table->string('message_id')->nullable()->comment('SMTP Message-ID header');
            $table->string('in_reply_to')->nullable()->comment('In-Reply-To header for threading');
            $table->string('thread_id')->nullable()->index()->comment('Grouped thread identifier');

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', ['unread', 'read', 'replied', 'archived'])->default('unread')->index();
            $table->string('category')->nullable()->comment('Optional admin-assigned category label');

            // ── Reply tracking ───────────────────────────────────────────
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();

            // ── Timestamps ───────────────────────────────────────────────
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index('from_email');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_emails');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam Timetable Notification Log
 *
 * Records each manual "Exam Time Table Notification" batch sent by admin
 * from the Compose tab. Stores the full context so the admin can audit
 * what was sent, to whom, and when.
 *
 * Individual per-recipient delivery is still tracked in email_logs
 * (one row per student, email_type = 'exam_timetable').
 * This table stores the batch-level summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_timetable_notifications', function (Blueprint $table) {
            $table->id();

            // ── Sender ───────────────────────────────────────────────────
            $table->foreignId('sent_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // ── Academic group filters ────────────────────────────────────
            $table->foreignId('academic_year_id')
                  ->constrained('academic_years')
                  ->cascadeOnDelete();

            $table->foreignId('year_level_id')
                  ->constrained('year_levels')
                  ->cascadeOnDelete();

            $table->foreignId('major_id')
                  ->nullable()
                  ->constrained('majors')
                  ->nullOnDelete();

            $table->unsignedTinyInteger('semester');   // 1 or 2

            // ── Selected exam schedules ───────────────────────────────────
            // JSON array of exam_schedule IDs that were included
            $table->json('exam_schedule_ids');

            // ── Manual content from admin ─────────────────────────────────
            $table->text('exam_policy')->nullable();
            $table->text('additional_instructions')->nullable();

            // ── Send result ───────────────────────────────────────────────
            $table->unsignedInteger('recipient_count')->default(0);
            $table->enum('status', ['queued', 'sent', 'partial', 'failed'])->default('queued');
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['academic_year_id', 'year_level_id', 'semester'], 'etn_ay_yl_sem_idx');
            $table->index(['sent_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_timetable_notifications');
    }
};

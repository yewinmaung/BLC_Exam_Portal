<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam Security Extension — Phase 1
 *
 * Extends two existing tables to support the 3-tier violation policy:
 *
 *  exam_attempts:
 *    - status ENUM: adds 'terminated_pending_review' and 'rejected'
 *    - terminated_at   — when the exam was locked for review
 *    - approved_by     — FK to the admin/teacher who actioned the incident
 *    - approved_at     — when the action was taken
 *
 *  cheating_logs:
 *    - browser           — e.g. "Chrome 125"
 *    - device            — e.g. "Desktop"
 *    - os                — e.g. "Windows 11"
 *    - screen_resolution — e.g. "1920x1080"
 *    - timezone          — e.g. "Asia/Phnom_Penh"
 *    - ip_address        — request IP at time of violation
 *
 * All columns are nullable — fully backward compatible.
 * No existing columns or constraints are modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Extend exam_attempts ───────────────────────────────────────

        // MySQL requires a raw ALTER to modify an ENUM without dropping the column.
        // We append the two new values to the existing set.
        DB::statement("
            ALTER TABLE exam_attempts
            MODIFY COLUMN status ENUM(
                'in_progress',
                'submitted',
                'terminated',
                'suspicious',
                'terminated_pending_review',
                'rejected'
            ) NOT NULL DEFAULT 'in_progress'
        ");

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dateTime('terminated_at')
                  ->nullable()
                  ->after('expires_at')
                  ->comment('Set when status becomes terminated_pending_review');

            $table->foreignId('approved_by')
                  ->nullable()
                  ->after('terminated_at')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Admin or teacher who approved or rejected the incident');

            $table->dateTime('approved_at')
                  ->nullable()
                  ->after('approved_by')
                  ->comment('Timestamp of the approve/reject action');
        });

        // ── 2. Extend cheating_logs ───────────────────────────────────────

        Schema::table('cheating_logs', function (Blueprint $table) {
            $table->string('browser')->nullable()->after('warning_number')
                  ->comment('Browser name and version captured by client JS');

            $table->string('device')->nullable()->after('browser')
                  ->comment('Device type: Desktop / Mobile / Tablet');

            $table->string('os')->nullable()->after('device')
                  ->comment('Operating system name and version');

            $table->string('screen_resolution')->nullable()->after('os')
                  ->comment('Screen resolution e.g. 1920x1080');

            $table->string('timezone')->nullable()->after('screen_resolution')
                  ->comment('IANA timezone e.g. Asia/Phnom_Penh');

            $table->string('ip_address')->nullable()->after('timezone')
                  ->comment('IP address at time of violation');
        });
    }

    public function down(): void
    {
        // ── 1. Revert cheating_logs additions ─────────────────────────────

        Schema::table('cheating_logs', function (Blueprint $table) {
            $table->dropColumn([
                'browser',
                'device',
                'os',
                'screen_resolution',
                'timezone',
                'ip_address',
            ]);
        });

        // ── 2. Revert exam_attempts additions ─────────────────────────────

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['terminated_at', 'approved_by', 'approved_at']);
        });

        // Restore original ENUM (remove the two new values)
        DB::statement("
            ALTER TABLE exam_attempts
            MODIFY COLUMN status ENUM(
                'in_progress',
                'submitted',
                'terminated',
                'suspicious'
            ) NOT NULL DEFAULT 'in_progress'
        ");
    }
};

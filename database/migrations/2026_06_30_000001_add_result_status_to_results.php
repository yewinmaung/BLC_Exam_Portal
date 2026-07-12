<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent Examination Result Status Tracking
 *
 * Extends the `results` table with status classification fields so every
 * exam result is permanently categorised as:
 *   PASSED        — submitted and reached passing mark
 *   FAILED        — submitted but did not reach passing mark
 *   ABSENT        — never started or did not submit before expiry
 *   DISQUALIFIED  — removed by the anti-cheating system
 *
 * All new columns are nullable and backward-compatible.
 * Existing rows receive NULL and must be back-filled separately if needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Primary status classification — the single source of truth.
            $table->enum('exam_result_status', ['PASSED', 'FAILED', 'ABSENT', 'DISQUALIFIED'])
                  ->nullable()
                  ->after('is_published')
                  ->comment('Permanent result status: PASSED | FAILED | ABSENT | DISQUALIFIED');

            // Human-readable reason stored for DISQUALIFIED records.
            $table->text('violation_reason')
                  ->nullable()
                  ->after('exam_result_status')
                  ->comment('Exact violation reason for DISQUALIFIED results');

            // Timestamp when disqualification was recorded.
            $table->dateTime('disqualified_at')
                  ->nullable()
                  ->after('violation_reason')
                  ->comment('When the disqualification was issued');

            // Attendance — whether the student attended or was absent.
            $table->enum('attendance_status', ['attended', 'absent'])
                  ->nullable()
                  ->after('disqualified_at')
                  ->comment('attended = started the exam; absent = never started or expired without submission');

            // When the exam session ended (submitted or terminated).
            $table->dateTime('exam_finished_at')
                  ->nullable()
                  ->after('attendance_status')
                  ->comment('When the attempt was submitted or terminated');
        });

        // Back-fill existing rows: derive exam_result_status from is_passed and is_published
        // so historical records are consistent without a data-loss migration.
        DB::statement("
            UPDATE results
            SET exam_result_status = CASE
                WHEN is_passed = 1 THEN 'PASSED'
                ELSE 'FAILED'
            END,
            attendance_status = 'attended'
            WHERE exam_result_status IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn([
                'exam_result_status',
                'violation_reason',
                'disqualified_at',
                'attendance_status',
                'exam_finished_at',
            ]);
        });
    }
};

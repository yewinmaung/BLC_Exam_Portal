<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'absent' to the exam_attempts.status ENUM.
 *
 * Absent attempts are synthetic records created by the scheduler for students
 * who were enrolled in an exam but never started it. They carry:
 *   started_at   = NULL   (never started — the defining condition)
 *   submitted_at = NULL
 *   status       = 'absent'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE exam_attempts
            MODIFY COLUMN status ENUM(
                'in_progress',
                'submitted',
                'terminated',
                'suspicious',
                'terminated_pending_review',
                'rejected',
                'absent'
            ) NOT NULL DEFAULT 'in_progress'
        ");
    }

    public function down(): void
    {
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
    }
};

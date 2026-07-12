<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam Security Extension — Phase 2 adjustment
 *
 * Separates approval and rejection audit fields on exam_attempts so that:
 *   - approved_by / approved_at   → used exclusively when status → in_progress (approved)
 *   - rejected_by / rejected_at   → used exclusively when status → rejected
 *   - approval_comment            → queryable text written by approver
 *   - rejection_comment           → queryable text written by rejector
 *
 * All columns are nullable and fully backward compatible.
 * ActivityLog still receives the same entries — these columns make
 * the comments queryable without parsing log descriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            // ── Rejection actor ───────────────────────────────────────────
            $table->foreignId('rejected_by')
                  ->nullable()
                  ->after('approved_at')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Admin or teacher who rejected the security incident');

            $table->dateTime('rejected_at')
                  ->nullable()
                  ->after('rejected_by')
                  ->comment('Timestamp of the rejection action');

            // ── Queryable comments ────────────────────────────────────────
            $table->text('approval_comment')
                  ->nullable()
                  ->after('rejected_at')
                  ->comment('Free-text remark written by the approver');

            $table->text('rejection_comment')
                  ->nullable()
                  ->after('approval_comment')
                  ->comment('Free-text remark written by the rejector');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'rejected_by',
                'rejected_at',
                'approval_comment',
                'rejection_comment',
            ]);
        });
    }
};

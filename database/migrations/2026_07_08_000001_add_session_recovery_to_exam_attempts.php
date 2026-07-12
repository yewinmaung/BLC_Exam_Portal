<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds fields needed for the temporary exam session recovery flow.
 *
 *  exam_attempts:
 *    - disconnected_at   : when the temporary interruption was recorded
 *    - last_question_id  : the question the student was on when interrupted
 *
 *  session_recovery_logs:
 *    Full audit trail for every auto-recovery event (admin evidence).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add recovery tracking columns to exam_attempts
        Schema::table('exam_attempts', function (Blueprint $table) {
            // Timestamp when the temporary interruption was detected
            $table->timestamp('disconnected_at')->nullable()->after('terminated_at');
            // The question the student was on when interrupted
            $table->unsignedBigInteger('last_question_id')->nullable()->after('disconnected_at');
        });

        // Full audit log table for session recovery events
        Schema::create('session_recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();

            // Core disconnect/reconnect tracking
            $table->timestamp('disconnected_at');                     // when the session was interrupted
            $table->timestamp('reconnected_at')->nullable();          // when the student successfully resumed
            $table->unsignedInteger('disconnected_duration_seconds')->nullable(); // duration outside exam
            $table->string('disconnect_reason', 100)->nullable();     // e.g. "network_disconnect", "browser_close"
            $table->unsignedBigInteger('last_question_id')->nullable(); // question ID student was viewing

            // Browser / network fingerprint (evidence only — never used for enforcement)
            $table->json('browser_info')->nullable();                 // structured browser metadata
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();             // supports IPv6

            // Recovery outcome
            $table->enum('recovery_status', ['recovered', 'expired', 'pending'])
                  ->default('pending');                               // pending until student returns or window expires

            $table->timestamps();

            // Indexes for admin queries
            $table->index(['attempt_id', 'disconnected_at']);
            $table->index('recovery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_recovery_logs');

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['disconnected_at', 'last_question_id']);
        });
    }
};

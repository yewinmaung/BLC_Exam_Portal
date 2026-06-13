<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->dateTime('last_login_at')->nullable();
            $table->string('exam_session_token')->nullable();
            $table->softDeletes();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('enrolled_at')->useCurrent();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'published', 'closed'])->default('draft');
            $table->unsignedInteger('total_marks')->default(100);
            $table->unsignedInteger('passing_marks')->default(40);
            $table->boolean('shuffle_questions')->default(false);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('attempt_limit')->default(1);
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('question_categories')->nullOnDelete();
            $table->enum('type', ['mcq', 'true_false', 'essay', 'file_upload']);
            $table->longText('content_encrypted');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedInteger('marks')->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->longText('content_encrypted');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->enum('status', ['in_progress', 'submitted', 'terminated', 'suspicious'])->default('in_progress');
            $table->unsignedTinyInteger('warning_count')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('session_token')->nullable();
            $table->timestamps();
            $table->index(['exam_id', 'student_id']);
        });

        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained('answers')->nullOnDelete();
            $table->longText('answer_text')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('marks_awarded')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_marks');
            $table->unsignedInteger('obtained_marks');
            $table->decimal('percentage', 5, 2);
            $table->enum('grade', ['A', 'B', 'C', 'D', 'F'])->nullable();
            $table->boolean('is_passed')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('file_path')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(['sender_id', 'receiver_id']);
        });

        Schema::create('cheating_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('violation_type');
            $table->text('details')->nullable();
            $table->unsignedTinyInteger('warning_number')->default(1);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('attempt_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('teacher_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('admin_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_reset_requests');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('cheating_logs');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('results');
        Schema::dropIfExists('student_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_categories');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active', 'phone', 'last_login_at', 'exam_session_token', 'deleted_at']);
        });
        Schema::dropIfExists('roles');
    }
};

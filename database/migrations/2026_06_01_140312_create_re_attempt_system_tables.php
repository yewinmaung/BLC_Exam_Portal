<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('re_attempt_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_remark')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('re_attempt_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('re_attempt_requests')->cascadeOnDelete();
            $table->string('action');          // created, approved, rejected, updated
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('actor_role');      // admin, teacher
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_attempt_logs');
        Schema::dropIfExists('re_attempt_requests');
    }
};

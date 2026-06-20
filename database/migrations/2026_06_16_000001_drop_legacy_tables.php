<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attempt_reset_requests — DB confirmed empty, superseded by re_attempt_requests
        Schema::dropIfExists('attempt_reset_requests');

        // exam_import_logs — DB confirmed empty, model has zero callers
        Schema::dropIfExists('exam_import_logs');
    }

    public function down(): void
    {
        // Restore attempt_reset_requests
        Schema::create('attempt_reset_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
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

        // Restore exam_import_logs
        Schema::create('exam_import_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_type');
            $table->integer('questions_found')->default(0);
            $table->integer('questions_imported')->default(0);
            $table->text('parse_log')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('completed');
            $table->timestamps();
        });
    }
};

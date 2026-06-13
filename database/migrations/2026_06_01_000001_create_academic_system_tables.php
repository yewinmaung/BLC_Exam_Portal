<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic years e.g. 2025-2026
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // "2025-2026"
            $table->year('start_year');
            $table->year('end_year');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        // Year levels: Year 1 … Year 5
        Schema::create('year_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');   // 1-5
            $table->string('name');                 // "First Year"
            $table->string('department')->nullable();
            $table->string('major')->nullable();
            $table->timestamps();
        });

        // Permanent per-student per-year record
        Schema::create('student_year_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
            $table->string('semester')->default('1');   // 1 or 2
            $table->string('department')->nullable();
            $table->string('major')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->enum('status', ['active', 'promoted', 'failed', 'withdrawn'])->default('active');
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id', 'year_level_id', 'semester'], 'syr_unique');
        });

        // Aggregated yearly exam results (permanent archive)
        Schema::create('yearly_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('obtained_marks', 8, 2)->default(0);
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('grade')->nullable();
            $table->boolean('is_passed')->default(false);
            $table->string('semester')->default('1');
            $table->timestamps();
        });

        // Promotion history (never deleted)
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_year_level_id')->constrained('year_levels')->cascadeOnDelete();
            $table->foreignId('to_year_level_id')->constrained('year_levels')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promoted_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('promoted_at');
            $table->timestamps();
        });

        // Certificate log (serial numbers, permanent)
        Schema::create('certificate_logs', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['transcript', 'completion', 'promotion', 'achievement']);
            $table->string('issued_by');
            $table->string('qr_token')->unique();
            $table->timestamp('issued_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_logs');
        Schema::dropIfExists('promotion_histories');
        Schema::dropIfExists('yearly_exam_results');
        Schema::dropIfExists('student_year_records');
        Schema::dropIfExists('year_levels');
        Schema::dropIfExists('academic_years');
    }
};

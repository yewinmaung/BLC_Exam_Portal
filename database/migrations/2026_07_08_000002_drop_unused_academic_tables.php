<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused academic tables that were created but never implemented.
 *
 * Investigation findings:
 * - All 4 tables have no Model files
 * - All 4 tables have no controller/service references
 * - All 4 tables have no data
 * - yearly_transcripts is duplicate of student_year_records
 * - Features were planned but never implemented
 *
 * See: DATABASE_CLEANUP_FINDINGS.md for full analysis
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop unused academic tracking tables
        // These were created but features were never implemented
        
        // 1. yearly_exam_results - Superseded by results + student_year_records
        Schema::dropIfExists('yearly_exam_results');
        
        // 2. promotion_histories - Promotion feature never implemented
        Schema::dropIfExists('promotion_histories');
        
        // 3. certificate_logs - Certificate generation never implemented
        Schema::dropIfExists('certificate_logs');
        
        // 4. yearly_transcripts - Duplicate of student_year_records
        Schema::dropIfExists('yearly_transcripts');
    }

    public function down(): void
    {
        // Recreate yearly_exam_results
        Schema::create('yearly_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
            $table->decimal('cumulative_gpa', 3, 2)->nullable();
            $table->unsignedInteger('exams_taken')->default(0);
            $table->unsignedInteger('exams_passed')->default(0);
            $table->unsignedInteger('exams_failed')->default(0);
            $table->timestamps();
        });

        // Recreate promotion_histories
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_year_level_id')->constrained('year_levels')->cascadeOnDelete();
            $table->foreignId('to_year_level_id')->constrained('year_levels')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->enum('promotion_type', ['promoted', 'retained', 'graduated']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Recreate certificate_logs
        Schema::create('certificate_logs', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->enum('certificate_type', ['completion', 'graduation', 'transcript']);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        // Recreate yearly_transcripts
        Schema::create('yearly_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
            $table->decimal('cumulative_gpa', 3, 2)->nullable();
            $table->unsignedInteger('credits_earned')->default(0);
            $table->unsignedInteger('credits_attempted')->default(0);
            $table->unsignedInteger('rank_in_class')->nullable();
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->timestamps();
        });
    }
};

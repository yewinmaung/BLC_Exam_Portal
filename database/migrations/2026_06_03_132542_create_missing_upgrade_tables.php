<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yearly transcript records
        if (!Schema::hasTable('yearly_transcripts')) {
            Schema::create('yearly_transcripts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->foreignId('year_level_id')->constrained()->cascadeOnDelete();
                $table->string('semester');
                $table->decimal('gpa', 4, 2)->nullable();
                $table->decimal('total_marks', 8, 2)->default(0);
                $table->decimal('obtained_marks', 8, 2)->default(0);
                $table->decimal('percentage', 5, 2)->default(0);
                $table->string('grade')->nullable();
                $table->boolean('is_passed')->default(false);
                $table->enum('status', ['draft', 'published'])->default('published');
                $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['student_id','academic_year_id','year_level_id','semester'], 'yt_unique');
            });
        }

        // Exam import logs (Moodle-style)
        if (!Schema::hasTable('exam_import_logs')) {
            Schema::create('exam_import_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
                $table->string('original_filename');
                $table->string('file_type');        // pdf, doc, docx, txt
                $table->integer('questions_found')->default(0);
                $table->integer('questions_imported')->default(0);
                $table->text('parse_log')->nullable();
                $table->enum('status', ['processing', 'completed', 'failed'])->default('completed');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_import_logs');
        Schema::dropIfExists('yearly_transcripts');
    }
};

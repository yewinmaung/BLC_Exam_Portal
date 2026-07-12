<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course Module Redesign — Phase 1
 *
 * Creates the `majors` table and adds:
 *   - courses.major_id          (nullable FK — null = available to all majors / year 1)
 *   - courses.teacher_id        already exists; kept as-is
 *   - enrollments.year_level_id (FK → year_levels; mirrors the integer `year` for proper relational integrity)
 *   - enrollments.major_id      (nullable FK — null = no major restriction / year 1 students)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. majors ─────────────────────────────────────────────────────
        Schema::create('majors', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // e.g. "Computer Science"
            $table->string('code')->unique(); // e.g. "CS", "CT"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 2. courses — add major_id ─────────────────────────────────────
        // nullable: Year 1 courses have no major requirement
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('major_id')
                  ->nullable()
                  ->after('semester')
                  ->constrained('majors')
                  ->nullOnDelete()
                  ->comment('null = all majors (First Year); set for Year 2+');
        });

        // ── 3. enrollments — add year_level_id + major_id ────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            // year_level_id: proper FK replacing the loose integer `year` column
            $table->foreignId('year_level_id')
                  ->nullable()
                  ->after('year')
                  ->constrained('year_levels')
                  ->nullOnDelete()
                  ->comment('FK to year_levels; mirrors `year` integer for relational scoping');

            // major_id: null for Year 1 students
            $table->foreignId('major_id')
                  ->nullable()
                  ->after('year_level_id')
                  ->constrained('majors')
                  ->nullOnDelete()
                  ->comment('null = no major (Year 1); set for Year 2+');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['major_id']);
            $table->dropForeign(['year_level_id']);
            $table->dropColumn(['major_id', 'year_level_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['major_id']);
            $table->dropColumn('major_id');
        });

        Schema::dropIfExists('majors');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('enrollments', 'year')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->unsignedTinyInteger('year')->default(1)->after('student_id')
                    ->comment('Academic year level 1–5');
            });
        }

        // Replace unique (course_id, student_id) with (course_id, student_id, year).
        try {
            DB::statement('ALTER TABLE enrollments DROP FOREIGN KEY enrollments_student_id_foreign');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_course_id_student_id_unique');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_course_student_year_unique');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE enrollments ADD UNIQUE KEY enrollments_course_student_year_unique (course_id, student_id, year)');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {
        }

        // Add target_year to exam_schedules
        if (!Schema::hasColumn('exam_schedules', 'target_year')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->unsignedTinyInteger('target_year')->nullable()->after('attempt_limit')
                      ->comment('Target academic year 1–5, NULL = all years');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_schedules', 'target_year')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->dropColumn('target_year');
            });
        }

        try {
            DB::statement('ALTER TABLE enrollments DROP FOREIGN KEY enrollments_student_id_foreign');
            DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_course_student_year_unique');
            DB::statement('ALTER TABLE enrollments ADD UNIQUE KEY enrollments_course_id_student_id_unique (course_id, student_id)');
            DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {}

        if (Schema::hasColumn('enrollments', 'year')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropColumn('year');
            });
        }
    }
};

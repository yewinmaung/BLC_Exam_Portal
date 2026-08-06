<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable record_type column to student_year_records.
 * NULL / 'NORMAL' = existing behaviour (backward compatible).
 * 'TRANSFER'      = student may start from any year level.
 * 'READMISSION'   = same sequential rules as NORMAL but academic year gaps are allowed.
 *
 * Note: the remark column is added in migration 2026_07_31_000002.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_year_records', function (Blueprint $table) {
            $table->string('record_type', 20)
                  ->nullable()
                  ->default(null)
                  ->after('status')
                  ->comment('NULL or NORMAL = default progression; TRANSFER = may start at any year; READMISSION = gaps allowed');
        });
    }

    public function down(): void
    {
        Schema::table('student_year_records', function (Blueprint $table) {
            $table->dropColumn('record_type');
        });
    }
};

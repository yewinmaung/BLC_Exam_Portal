<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable remark column to student_year_records.
 * Required for TRANSFER and READMISSION record types; optional for NORMAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_year_records', function (Blueprint $table) {
            $table->text('remark')
                  ->nullable()
                  ->after('record_type')
                  ->comment('Required for TRANSFER and READMISSION types; optional for NORMAL');
        });
    }

    public function down(): void
    {
        Schema::table('student_year_records', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};

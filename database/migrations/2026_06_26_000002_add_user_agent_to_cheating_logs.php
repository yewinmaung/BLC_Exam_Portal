<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam Security Extension — Phase 2 addendum
 *
 * Adds the raw User-Agent string to cheating_logs so the original browser
 * fingerprint is always preserved for forensic purposes, independently of
 * the parsed browser/device/os fields added in migration 000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheating_logs', function (Blueprint $table) {
            $table->text('user_agent')
                  ->nullable()
                  ->after('warning_number')
                  ->comment('Raw navigator.userAgent string captured by client JS');
        });
    }

    public function down(): void
    {
        Schema::table('cheating_logs', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};

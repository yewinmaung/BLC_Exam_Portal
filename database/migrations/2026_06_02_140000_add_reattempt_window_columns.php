<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('re_attempt_requests', 're_attempt_start_at')) {
                $table->timestamp('re_attempt_start_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('re_attempt_requests', 're_attempt_end_at')) {
                $table->timestamp('re_attempt_end_at')->nullable()->after('re_attempt_start_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (Schema::hasColumn('re_attempt_requests', 're_attempt_end_at')) {
                $table->dropColumn('re_attempt_end_at');
            }
            if (Schema::hasColumn('re_attempt_requests', 're_attempt_start_at')) {
                $table->dropColumn('re_attempt_start_at');
            }
        });
    }
};

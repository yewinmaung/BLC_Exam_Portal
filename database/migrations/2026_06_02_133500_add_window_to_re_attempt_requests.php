<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('re_attempt_requests', 'window_starts_at')) {
                $table->timestamp('window_starts_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('re_attempt_requests', 'window_ends_at')) {
                $table->timestamp('window_ends_at')->nullable()->after('window_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (Schema::hasColumn('re_attempt_requests', 'window_ends_at')) {
                $table->dropColumn('window_ends_at');
            }
            if (Schema::hasColumn('re_attempt_requests', 'window_starts_at')) {
                $table->dropColumn('window_starts_at');
            }
        });
    }
};

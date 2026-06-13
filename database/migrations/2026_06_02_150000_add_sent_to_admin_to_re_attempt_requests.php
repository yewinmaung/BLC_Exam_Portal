<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('re_attempt_requests', 'sent_to_admin_at')) {
                $table->timestamp('sent_to_admin_at')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_attempt_requests', function (Blueprint $table) {
            if (Schema::hasColumn('re_attempt_requests', 'sent_to_admin_at')) {
                $table->dropColumn('sent_to_admin_at');
            }
        });
    }
};

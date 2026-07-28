<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds force_password_change flag to users.
 *
 * Default: false — existing users are unaffected.
 * Set to true only when admin creates a new Teacher or Student account
 * so they are forced to change the temporary password on first login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('force_password_change')
                  ->default(false)
                  ->after('is_active')
                  ->comment('Forces user to change password on next login (set for new Teacher/Student accounts)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('force_password_change');
        });
    }
};

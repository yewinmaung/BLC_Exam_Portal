<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds login security columns to the users table.
 *
 * failed_login_attempts       – incremented on each wrong password; reset on success
 * locked_until                – when set and in the future, login is blocked
 * temporary_password_expires_at – set when admin creates account; login rejected after this
 *
 * Existing users get NULL / 0 defaults → no behaviour change for current accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_login_attempts')
                  ->default(0)
                  ->after('force_password_change');

            $table->timestamp('locked_until')
                  ->nullable()
                  ->after('failed_login_attempts');

            $table->timestamp('temporary_password_expires_at')
                  ->nullable()
                  ->after('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'temporary_password_expires_at',
            ]);
        });
    }
};

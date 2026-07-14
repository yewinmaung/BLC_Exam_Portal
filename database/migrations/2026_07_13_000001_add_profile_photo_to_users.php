<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `profile_photo` to users.
 *
 * Stores the path to the user's uploaded avatar relative to the
 * public disk (storage/app/public).  NULL means no custom photo —
 * the UI falls back to the initials avatar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo')->nullable()->after('phone')
                ->comment('Path on public disk, e.g. avatars/42.webp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_photo');
        });
    }
};

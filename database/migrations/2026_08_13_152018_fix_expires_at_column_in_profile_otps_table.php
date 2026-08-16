<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: expires_at was defined as non-nullable TIMESTAMP, which caused MySQL to
 * apply ON UPDATE CURRENT_TIMESTAMP automatically — overwriting expires_at with
 * the current time on every UPDATE (including attempts increment).
 * Making it nullable removes that implicit MySQL behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to avoid doctrine/dbal dependency
        \DB::statement('ALTER TABLE profile_otps MODIFY expires_at TIMESTAMP NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Use raw SQL to revert
        \DB::statement('ALTER TABLE profile_otps MODIFY expires_at TIMESTAMP NOT NULL');
    }
};

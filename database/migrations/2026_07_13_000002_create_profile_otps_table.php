<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores single-use OTPs for password-change verification.
 *
 * Rules enforced at the application layer:
 *  - 6-digit code, hashed with bcrypt before storage
 *  - Valid for 5 minutes (expires_at)
 *  - One-time use (used_at is set on first valid verification)
 *  - Max 5 wrong attempts per OTP record (attempts column)
 *  - Resend allowed only if 60 s have elapsed since created_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');            // bcrypt of 6-digit code
            $table->string('new_password_hash');    // bcrypt of new password — stored here so we do NOT keep plaintext in session
            $table->tinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_otps');
    }
};

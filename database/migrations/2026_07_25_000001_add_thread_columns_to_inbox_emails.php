<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds email conversation threading columns to inbox_emails.
 *
 * New columns
 * -----------
 *  parent_id   – FK to inbox_emails.id of the message this is a direct reply to.
 *                NULL for root/start-of-thread messages.
 *  references  – RFC 2822 References header value (space-separated Message-IDs).
 *                Used to reconstruct full thread ancestry even when parent is missing.
 *
 * Existing columns kept as-is
 *  thread_id   – already exists; now populated with a canonical string key
 *                (MD5 of the root message's Message-ID). All messages in the
 *                same conversation share the same thread_id.
 *  in_reply_to – already exists; the raw In-Reply-To header value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_emails', function (Blueprint $table) {
            // Self-referential FK to the direct parent message
            $table->unsignedBigInteger('parent_id')
                  ->nullable()
                  ->after('in_reply_to');

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('inbox_emails')
                  ->nullOnDelete();

            // Full RFC 2822 References header (may be very long)
            $table->text('references')
                  ->nullable()
                  ->after('parent_id');

            // Index thread_id for fast thread fetches (column already exists)
            // Only add if not already present — check SM_SCHEMA first.
            $indexExists = collect(\Illuminate\Support\Facades\DB::select(
                "SHOW INDEX FROM inbox_emails WHERE Key_name = 'inbox_emails_thread_id_index'"
            ))->isNotEmpty();

            if (!$indexExists) {
                $table->index('thread_id', 'inbox_emails_thread_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inbox_emails', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'references']);
        });
    }
};

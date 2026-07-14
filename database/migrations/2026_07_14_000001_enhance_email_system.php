<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhances the email system with:
 *  - CC support on email_logs
 *  - email_type column for better categorisation
 *  - email_campaigns table with approval workflow
 *  - campaign_id FK on email_logs
 *
 * NOTE: email_campaigns is created BEFORE the FK on email_logs is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Email Campaigns (create first so FK on email_logs can reference it) ──
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject');
            $table->longText('body_html');
            $table->string('recipients');          // group key from ScheduledEmail::$recipientLabels
            $table->string('template_slug')->nullable();

            // Approval workflow
            $table->enum('status', [
                'draft',            // being composed
                'pending_approval', // submitted, awaiting admin
                'approved',         // admin approved — will be queued for sending
                'rejected',         // admin rejected
                'processing',       // queue is actively sending
                'sent',             // all recipients dispatched
                'failed',           // dispatch failed
            ])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();

            // Progress counters
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // ── 2. Enhance email_logs ─────────────────────────────────────────
        Schema::table('email_logs', function (Blueprint $table) {
            // CC support — single CC address (matches the spec requirement)
            $table->string('cc_email')->nullable()->after('to_name');
            $table->string('cc_name')->nullable()->after('cc_email');

            // Categorise for admin filtering
            $table->string('email_type')->nullable()->after('event')
                ->comment('otp|password_changed|result|cheating|announcement|schedule|bulk|test|welcome|security');

            // Campaign FK (null for transactional emails)
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('email_type')
                ->constrained('email_campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['cc_email', 'cc_name', 'email_type', 'campaign_id']);
        });

        Schema::dropIfExists('email_campaigns');
    }
};

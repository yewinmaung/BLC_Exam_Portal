<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an inbound email in the admin inbox.
 *
 * Phase 1: records are created manually (test/demo).
 * Phase 2 will add IMAP / webhook population.
 *
 * @property int         $id
 * @property string      $from_email
 * @property string|null $from_name
 * @property string      $sender_type    student|external
 * @property int|null    $user_id
 * @property string      $subject
 * @property string|null $body_html
 * @property string|null $body_text
 * @property string|null $message_id
 * @property string|null $in_reply_to
 * @property string|null $thread_id
 * @property string      $status         unread|read|replied|archived
 * @property string|null $category
 * @property int|null    $replied_by
 * @property \Carbon\Carbon|null $replied_at
 * @property \Carbon\Carbon      $received_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class InboxEmail extends Model
{
    protected $table = 'inbox_emails';

    protected $fillable = [
        'from_email',
        'from_name',
        'sender_type',
        'user_id',
        'subject',
        'body_html',
        'body_text',
        'message_id',
        'in_reply_to',
        'thread_id',
        'status',
        'category',
        'replied_by',
        'replied_at',
        'received_at',
    ];

    protected $casts = [
        'replied_at'  => 'datetime',
        'received_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    /** The user account linked to the sender (student or registered user). */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The admin who sent the reply. */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isUnread(): bool   { return $this->status === 'unread'; }
    public function isRead(): bool     { return $this->status === 'read'; }
    public function isReplied(): bool  { return $this->status === 'replied'; }
    public function isArchived(): bool { return $this->status === 'archived'; }

    /** Display name — use from_name when available, otherwise the raw email. */
    public function getDisplayNameAttribute(): string
    {
        return $this->from_name ?: $this->from_email;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
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
 * @property string|null $references
 * @property string|null $thread_id
 * @property int|null    $parent_id
 * @property string      $status         unread|read|replied|archived
 * @property string|null $category
 * @property int|null    $replied_by
 * @property \Carbon\Carbon|null $replied_at
 * @property \Carbon\Carbon      $received_at
 */
class InboxEmail extends Model
{
    protected $table = 'inbox_emails';

    protected $fillable = [
        'from_email', 'from_name', 'sender_type', 'user_id',
        'subject', 'body_html', 'body_text',
        'message_id', 'in_reply_to', 'references', 'thread_id', 'parent_id',
        'status', 'category',
        'replied_by', 'replied_at', 'received_at',
    ];

    protected $casts = [
        'replied_at'  => 'datetime',
        'received_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /** Direct parent message in the thread. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct children (replies to this message). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('received_at');
    }

    /**
     * All messages in the same thread, ordered oldest → newest.
     * Uses thread_id for a flat fetch (avoids recursive CTEs).
     */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id', 'thread_id')
                    ->orderBy('received_at');
    }

    // ── Thread helpers ─────────────────────────────────────────────────────

    /** True if this message has siblings (i.e., it belongs to a multi-message thread). */
    public function isInThread(): bool
    {
        return $this->thread_id !== null
            && static::where('thread_id', $this->thread_id)->count() > 1;
    }

    /** Number of messages in this thread (including this one). */
    public function threadCount(): int
    {
        if (!$this->thread_id) return 1;
        return static::where('thread_id', $this->thread_id)->count();
    }

    /** Returns the root message of the thread (earliest received_at). */
    public function threadRoot(): self
    {
        if (!$this->thread_id) return $this;
        return static::where('thread_id', $this->thread_id)
                     ->orderBy('received_at')
                     ->first() ?? $this;
    }

    // ── Status helpers ─────────────────────────────────────────────────────

    public function isUnread(): bool   { return $this->status === 'unread'; }
    public function isRead(): bool     { return $this->status === 'read'; }
    public function isReplied(): bool  { return $this->status === 'replied'; }
    public function isArchived(): bool { return $this->status === 'archived'; }

    public function getDisplayNameAttribute(): string
    {
        return $this->from_name ?: $this->from_email;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'to_email', 'to_name', 'cc_email', 'cc_name',
        'from_email', 'from_name',
        'subject', 'body_html', 'template_slug', 'event',
        'email_type', 'campaign_id',
        'status', 'provider', 'error', 'message_id',
        'user_id', 'queued_at', 'sent_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(string $messageId = null): void
    {
        $this->update([
            'status'     => 'sent',
            'sent_at'    => now(),
            'message_id' => $messageId,
            'error'      => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error'  => $error,
        ]);
    }
}

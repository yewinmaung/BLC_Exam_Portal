<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class ChatMessage extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'message', 'file_path', 'is_read',
    ];

    protected $casts = ['is_read' => 'boolean'];

    // ── Encrypt on save ──────────────────────────────────────────
    public function setMessageAttribute(?string $value): void
    {
        $this->attributes['message'] = ($value !== null && $value !== '')
            ? Crypt::encryptString($value)
            : '';
    }

    // ── Decrypt on read ──────────────────────────────────────────
    public function getMessageAttribute(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Already plain text (legacy messages before encryption was added)
            return $value;
        }
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

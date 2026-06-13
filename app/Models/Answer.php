<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = [
        'question_id', 'content_encrypted', 'is_correct', 'is_blank_answer', 'order',
    ];

    protected $casts = [
        'is_correct'      => 'boolean',
        'is_blank_answer' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function getDecryptedContentAttribute(): ?string
    {
        return app(EncryptionService::class)->decrypt($this->content_encrypted);
    }
}

<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'exam_id', 'category_id', 'type', 'content_encrypted',
        'attachment_path', 'attachment_name', 'attachment_mime',
        'difficulty', 'marks', 'order',
    ];

    public function isFillBlank(): bool
    {
        return $this->type === 'fill_blank';
    }

    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path
            ? Storage::disk('public')->url($this->attachment_path)
            : null;
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('order');
    }

    public function getDecryptedContentAttribute(): ?string
    {
        return app(EncryptionService::class)->decrypt($this->content_encrypted);
    }
}

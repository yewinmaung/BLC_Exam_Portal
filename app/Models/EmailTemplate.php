<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'subject', 'body_html', 'body_text',
        'event', 'is_active', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    /** Replace {{variables}} in subject and body with real values. */
    public function render(array $vars = []): array
    {
        $subject  = $this->subject;
        $bodyHtml = $this->body_html;
        $bodyText = $this->body_text ?? strip_tags($bodyHtml);

        foreach ($vars as $key => $value) {
            $subject  = str_replace('{{'.$key.'}}', $value, $subject);
            $bodyHtml = str_replace('{{'.$key.'}}', e($value), $bodyHtml);
            $bodyText = str_replace('{{'.$key.'}}', $value, $bodyText);
        }

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

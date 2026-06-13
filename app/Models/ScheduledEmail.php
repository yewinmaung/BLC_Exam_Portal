<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledEmail extends Model
{
    protected $fillable = [
        'name', 'template_slug', 'subject', 'body_html',
        'recipients', 'send_at', 'is_sent', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
    ];

    public static array $recipientLabels = [
        'all_students'   => 'All Students',
        'first_year'     => 'First Year Students',
        'second_year'    => 'Second Year Students',
        'third_year'     => 'Third Year Students',
        'fourth_year'    => 'Fourth Year Students',
        'final_year'     => 'Final Year Students',
        'all_teachers'   => 'All Teachers',
        'all_users'      => 'All Users',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

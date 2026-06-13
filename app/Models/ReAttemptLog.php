<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReAttemptLog extends Model
{
    protected $table = 're_attempt_logs';

    protected $fillable = ['request_id', 'action', 'actor_id', 'actor_role', 'remarks'];

    public function request(): BelongsTo { return $this->belongsTo(ReAttemptRequest::class, 'request_id'); }
    public function actor(): BelongsTo   { return $this->belongsTo(User::class, 'actor_id'); }
}

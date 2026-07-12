<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Write an immutable audit log entry.
     *
     * @param  string              $action       Machine-readable action key.
     * @param  string|array|null   $description  Human-readable text OR a structured
     *                                           metadata array (stored as JSON).
     *                                           Pass an array for security audit events
     *                                           so individual keys remain queryable via
     *                                           JSON_EXTRACT / whereJsonContains.
     * @param  Model|null          $model        The subject Eloquent model, if any.
     */
    public function log(string $action, string|array|null $description = null, ?Model $model = null): void
    {
        // If a native array is passed, encode it — keeps the DB column as text
        // while making structured data queryable via JSON_EXTRACT.
        if (is_array($description)) {
            $description = json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id'   => $model?->id,
            'description'=> $description,
            'ip_address' => Request::ip(),
        ]);
    }
}

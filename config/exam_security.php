<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary Session Recovery Time Limit (seconds)
    |--------------------------------------------------------------------------
    | If a student's exam session is temporarily interrupted (e.g. network
    | disconnect) and the attempt enters 'terminated_pending_review' status,
    | this is the window in which the student can auto-resume WITHOUT admin
    | approval. After this window expires the attempt stays locked and
    | requires the normal admin review workflow.
    |
    | Default: 600 seconds (10 minutes)
    */
    'recovery_time_limit' => env('EXAM_RECOVERY_TIME_LIMIT', 600),

];

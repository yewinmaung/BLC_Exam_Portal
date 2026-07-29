<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary Session Recovery Time Limit (seconds)
    |--------------------------------------------------------------------------
    | If a student's exam session is temporarily interrupted (e.g. network
    | disconnect or browser close), this is the maximum number of seconds after
    | the disconnect timestamp in which the student may auto-resume WITHOUT
    | consuming an attempt or requiring admin action.
    |
    | Recovery is only permitted when ALL of the following are true:
    |   1. Elapsed since disconnect ≤ recovery_time_limit
    |   2. The attempt's expires_at has not passed
    |      (expires_at = MIN(started_at + duration, schedule.ends_at))
    |
    | After this window expires the attempt is auto-submitted and graded
    | using the student's existing saved answers.
    |
    | Default: 600 seconds (10 minutes)
    */
    'recovery_time_limit' => env('EXAM_RECOVERY_TIME_LIMIT', 600),

];

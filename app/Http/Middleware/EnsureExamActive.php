<?php

namespace App\Http\Middleware;

use App\Models\ExamAttempt;
use Closure;
use Illuminate\Http\Request;

/**
 * EnsureExamActive
 *
 * Rejects any request that targets an ExamAttempt that is no longer active.
 *
 * Since the new recovery workflow keeps status = 'in_progress' during a
 * temporary disconnect, the only check needed is isActive().
 * The old special-case passthrough for 'terminated_pending_review' is removed.
 *
 * Routes protected:
 *   GET  attempt/{attempt}/take       student.exam.take
 *   POST attempt/{attempt}/save       student.exam.save
 *   POST attempt/{attempt}/violation  student.exam.violation
 *   POST attempt/{attempt}/submit     student.exam.submit
 *
 * The disconnect endpoint (POST attempt/{attempt}/disconnect) does NOT carry
 * this middleware because it must be reachable even during a page-unload.
 */
class EnsureExamActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ExamAttempt|null $attempt */
        $attempt = $request->route('attempt');

        if (! ($attempt instanceof ExamAttempt)) {
            return $next($request);
        }

        // Normal path: attempt is active (status = in_progress).
        // A disconnected attempt still has status = in_progress under the new
        // workflow, so it passes through here automatically.
        if ($attempt->isActive()) {
            return $next($request);
        }

        // Attempt is not active — return the appropriate response.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'terminated' => true,
                'locked'     => false,
                'message'    => $this->message($attempt),
                'redirect'   => route('student.exams.index'),
            ], 403);
        }

        return redirect()
            ->route('student.exams.index')
            ->with('error', $this->message($attempt));
    }

    private function message(ExamAttempt $attempt): string
    {
        return match ($attempt->status) {
            'terminated_pending_review' =>
                'Your exam session is locked pending a security review.',
            'rejected' =>
                'Your exam session has been rejected following a security review.',
            'submitted' =>
                'This exam has already been submitted.',
            'terminated' =>
                'Your exam was terminated due to security violations.',
            default =>
                'This exam session is no longer active.',
        };
    }
}

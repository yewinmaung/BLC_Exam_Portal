<?php

namespace App\Console\Commands;

use App\Models\ExamAttempt;
use App\Services\SessionRecoveryService;
use Illuminate\Console\Command;

/**
 * FinalizeExpiredRecoverySessions
 *
 * Automatically finalizes exam attempts that are stuck in a disconnected state
 * after their recovery window (or exam expiry) has elapsed, without the student
 * returning to the page.
 *
 * SCOPE (all three conditions trigger finalization):
 *   1. status = in_progress AND disconnected_at IS NOT NULL
 *      AND disconnected_at + recovery_time_limit < now()    → recovery window expired
 *   2. status = in_progress AND disconnected_at IS NOT NULL
 *      AND expires_at < now()                                → exam expired first
 *
 * INVARIANTS (mirrors SessionRecoveryService::finalizeExpiredSession):
 *   - The SAME existing ExamAttempt is used (no new attempt created).
 *   - ALL student_answers already saved are graded.
 *   - Unanswered questions receive 0 marks (no student_answers row).
 *   - disconnected_at, expires_at, and student_answers are never deleted.
 *
 * Runs every minute via the scheduler (see Console\Kernel).
 * withoutOverlapping() prevents stacking on slow DB.
 */
class FinalizeExpiredRecoverySessions extends Command
{
    protected $signature   = 'sessions:finalize-expired
                              {--dry-run : Show what would be finalized without writing}';
    protected $description = 'Finalize disconnected exam attempts whose recovery window or exam time has expired';

    public function __construct(private SessionRecoveryService $recovery)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $recoveryLimit = (int) config('exam_security.recovery_time_limit', 300);
        $dryRun        = (bool) $this->option('dry-run');
        $finalized     = 0;

        // Find all disconnected in-progress attempts where EITHER:
        //   (a) the recovery window has expired:  disconnected_at + limit < now
        //   (b) the exam itself has expired:      expires_at < now
        //
        // Both conditions require finalization of the same attempt.
        // Using a raw whereRaw for the time arithmetic to keep it DB-portable.
        $expired = ExamAttempt::where('status', 'in_progress')
            ->whereNotNull('disconnected_at')
            ->where(function ($q) use ($recoveryLimit) {
                // (a) recovery window elapsed
                $q->whereRaw("disconnected_at + INTERVAL ? SECOND < NOW()", [$recoveryLimit])
                  // (b) exam expires_at passed
                  ->orWhere('expires_at', '<', now());
            })
            ->get();

        foreach ($expired as $attempt) {
            if ($dryRun) {
                $this->line(
                    "[DRY RUN] Would finalize attempt #{$attempt->id} "
                    . "(exam #{$attempt->exam_id}, student #{$attempt->student_id}) "
                    . "— disconnected_at: {$attempt->disconnected_at}, "
                    . "expires_at: {$attempt->expires_at}"
                );
                $finalized++;
                continue;
            }

            // Delegate to the existing finalization path in SessionRecoveryService.
            // This grades ALL saved answers and marks the attempt submitted.
            // No new attempt is created; no answers are discarded.
            $this->recovery->finalizeForScheduler($attempt);
            $finalized++;
        }

        if ($dryRun) {
            $this->warn("Dry run: {$finalized} attempt(s) would be finalized.");
        } else {
            $this->info("Finalized {$finalized} expired disconnected attempt(s).");
        }

        return self::SUCCESS;
    }
}

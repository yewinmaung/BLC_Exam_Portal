<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Result;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * NotifyStudentResults
 *
 * Sends "Result Available" in-app notifications to students after the exam
 * schedule window has closed.
 *
 * WHY a scheduled command (not inline in GradingService):
 *   Students may submit before the exam end time. Grading runs immediately
 *   at submission. However, the notification must not be sent until the
 *   schedule's ends_at has passed — so that no student sees their result
 *   before the exam window closes for everyone.
 *
 * Trigger condition (ALL must be true):
 *   1. The exam schedule ends_at < now()            — window has closed
 *   2. The result exists (was graded after submission)
 *   3. The attempt status is 'submitted'            — legitimate completion only
 *   4. The result is NOT DISQUALIFIED               — security terminations excluded
 *   5. The result is NOT ABSENT                     — absent students have no result to view
 *   6. No existing 'exam_result' UserNotification exists for this exact
 *      (user_id, type, link) triple — one notification per student per exam
 *
 * DUPLICATE PROTECTION:
 *   The deduplication key is the exact triple:
 *     user_id  = student's user ID
 *     type     = 'exam_result'
 *     link     = route('student.exams.show', $exam)   ← per-exam URL
 *
 *   This precisely identifies: same student + same exam + same release event.
 *   No LIKE queries, no free-text matching. The link is a deterministic URL
 *   generated from the exam's primary key — cannot collide across exams.
 *   The command is fully idempotent. Running it multiple times is safe.
 *
 * Registered in Console\Kernel to run everyMinute() with withoutOverlapping().
 * This mirrors the existing MarkAbsentResults command pattern.
 *
 * No new tables. No new columns. Uses existing NotificationService and
 * user_notifications table unchanged.
 */
class NotifyStudentResults extends Command
{
    protected $signature = 'results:notify-students
                            {--exam= : Only process a specific exam ID}
                            {--dry-run : Show what would be notified without writing}';

    protected $description = 'Send "Result Available" notifications to students after exam schedule ends';

    public function __construct(private NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $sent    = 0;
        $skipped = 0;

        // ── Find exam schedules whose window has closed ───────────────────
        // Only schedules with ends_at in the past are eligible.
        // is_published check is intentionally omitted: once a schedule ends,
        // it may have been unpublished (exam closed) but results still exist
        // and students are owed their notification.
        $scheduleQuery = ExamSchedule::where('ends_at', '<', now())
            ->with(['exam']);

        if ($this->option('exam')) {
            $scheduleQuery->where('exam_id', (int) $this->option('exam'));
        }

        $schedules = $scheduleQuery->get();

        if ($schedules->isEmpty()) {
            $this->info('No ended exam schedules found.');
            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $exam = $schedule->exam;

            if (! $exam) {
                continue;
            }

            // ── Compute the exact link for this exam ──────────────────────
            // This link is the deduplication key. It is a fixed, per-exam URL
            // derived from the exam's primary key — unique per exam, stable
            // across repeated command runs.
            $notificationLink = route('student.exams.show', $exam->id);

            // ── Find all eligible results for this exam ───────────────────
            $results = Result::where('exam_id', $exam->id)
                ->whereNotIn('exam_result_status', [
                    Result::STATUS_DISQUALIFIED,
                    Result::STATUS_ABSENT,
                ])
                ->whereNotNull('attempt_id')
                ->with(['attempt'])
                ->get();

            foreach ($results as $result) {
                $attempt = $result->attempt;

                // Guard: attempt must exist and status must be 'submitted'.
                // 'terminated'  → ExamSecurityService violation-3 path (excluded)
                // 'suspicious'  → legacy CheatingDetectionService path (excluded)
                // Any other non-submitted status → excluded
                if (! $attempt || $attempt->status !== 'submitted') {
                    $skipped++;
                    continue;
                }

                $studentId = $result->student_id;

                // ── Duplicate protection ──────────────────────────────────
                // Deduplication key: (user_id, type='exam_result', link)
                //
                // This triple precisely identifies:
                //   same student      → user_id = $studentId
                //   same exam         → link    = route('student.exams.show', $exam->id)
                //   same release event → type   = 'exam_result'
                //
                // Exact equality on all three columns — no LIKE, no ambiguity.
                // If ANY exam_result notification exists for this student pointing
                // to this exam's show page, the notification was already sent.
                $alreadyNotified = UserNotification::where('user_id', $studentId)
                    ->where('type', 'exam_result')
                    ->where('link', $notificationLink)
                    ->exists();

                if ($alreadyNotified) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("[DRY RUN] Would notify student #{$studentId} for exam #{$exam->id} ({$exam->title})");
                    $sent++;
                    continue;
                }

                // ── Load student and send notification ────────────────────
                $student = User::find($studentId);

                if (! $student) {
                    $skipped++;
                    continue;
                }

                $this->notifications->notify(
                    $student,
                    'exam_result',
                    'Result Available',
                    "Your result for \"{$exam->title}\" is now available for you to view.",
                    $notificationLink
                );

                $sent++;
            }
        }

        if ($dryRun) {
            $this->warn("Dry run complete. Would notify {$sent} student(s). Skipped {$skipped}.");
        } else {
            $this->info("Done. Notified {$sent} student(s). Skipped {$skipped} (already notified or ineligible).");
        }

        return self::SUCCESS;
    }
}

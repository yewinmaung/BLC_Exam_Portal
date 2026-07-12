<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Result;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MarkAbsentResults
 *
 * Creates ABSENT result records for students who were enrolled in an exam
 * but never started it (no ExamAttempt with started_at IS NOT NULL).
 *
 * Conditions for marking a student ABSENT:
 *   1. The exam schedule has ended (ends_at < now).
 *   2. The student is enrolled in the exam's course.
 *   3. The student has NO attempt where started_at IS NOT NULL.
 *   4. The student does NOT already have a Result record for this exam.
 *
 * An expired timer does NOT produce ABSENT — if the student started the exam,
 * their final status can only be PASSED, FAILED, or DISQUALIFIED.
 *
 * This command is safe to run repeatedly — it is fully idempotent.
 * Run via the scheduler after each exam window closes.
 */
class MarkAbsentResults extends Command
{
    protected $signature = 'results:mark-absent
                            {--exam= : Only process a specific exam ID}
                            {--dry-run : Show what would be created without writing}';

    protected $description = 'Create ABSENT result records for students who never started a closed exam';

    public function handle(): int
    {
        // ── Determine scope ───────────────────────────────────────────────
        // Find exam schedules that have already ended.
        $scheduleQuery = ExamSchedule::where('ends_at', '<', now())
            ->where('is_published', true)
            ->with(['exam.course.enrollments']);

        if ($this->option('exam')) {
            $scheduleQuery->where('exam_id', (int) $this->option('exam'));
        }

        $schedules = $scheduleQuery->get();

        if ($schedules->isEmpty()) {
            $this->info('No ended exam schedules found.');
            return self::SUCCESS;
        }

        $created  = 0;
        $skipped  = 0;
        $dryRun   = (bool) $this->option('dry-run');

        foreach ($schedules as $schedule) {
            $exam = $schedule->exam;

            if (! $exam) {
                continue;
            }

            $course = $exam->course;

            if (! $course) {
                continue;
            }

            // All student IDs enrolled in this course.
            $enrolledStudentIds = Enrollment::where('course_id', $course->id)
                ->pluck('student_id')
                ->unique();

            if ($enrolledStudentIds->isEmpty()) {
                continue;
            }

            // Student IDs who have a Result record for this exam already.
            // These students are fully handled — skip them entirely.
            $alreadyHaveResult = Result::where('exam_id', $exam->id)
                ->whereIn('student_id', $enrolledStudentIds)
                ->pluck('student_id')
                ->unique();

            // Student IDs who started at least one attempt (started_at IS NOT NULL).
            // These students attended — they cannot be ABSENT.
            $startedStudentIds = ExamAttempt::where('exam_id', $exam->id)
                ->whereIn('student_id', $enrolledStudentIds)
                ->whereNotNull('started_at')
                ->pluck('student_id')
                ->unique();

            // Absent = enrolled − already has result − started an attempt.
            $absentStudentIds = $enrolledStudentIds
                ->diff($alreadyHaveResult)
                ->diff($startedStudentIds)
                ->values();

            foreach ($absentStudentIds as $studentId) {
                if ($dryRun) {
                    $this->line("[DRY RUN] Would mark student #{$studentId} ABSENT for exam #{$exam->id} ({$exam->title})");
                    $created++;
                    continue;
                }

                DB::transaction(function () use ($exam, $schedule, $studentId) {
                    Result::create([
                        'attempt_id'         => null,   // No attempt was ever made
                        'exam_id'            => $exam->id,
                        'student_id'         => $studentId,
                        'total_marks'        => $exam->total_marks,
                        'obtained_marks'     => 0,
                        'percentage'         => 0.00,
                        'grade'              => 'F',
                        'is_passed'          => false,
                        'is_published'       => true,
                        // ── Status fields ─────────────────────────────────
                        'exam_result_status' => Result::STATUS_ABSENT,
                        'attendance_status'  => Result::ATTENDANCE_ABSENT,
                        'exam_finished_at'   => null,   // Never finished — never started
                        'violation_reason'   => null,
                        'disqualified_at'    => null,
                    ]);
                });

                $created++;
            }

            $skipped += $alreadyHaveResult->count();
        }

        if ($dryRun) {
            $this->warn("Dry run complete. Would create {$created} ABSENT record(s). Skipped {$skipped} (already have results).");
        } else {
            $this->info("Done. Created {$created} ABSENT record(s). Skipped {$skipped} (already had results).");
        }

        return self::SUCCESS;
    }
}

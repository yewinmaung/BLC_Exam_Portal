<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Result;
use App\Models\StudentYearRecord;
use App\Models\User;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    /**
     * Teacher: Academic Result Summary — grouped hierarchy.
     *
     * Mirrors the Admin result summary exactly in logic and data shape, with
     * one critical difference: every exam query is scoped to
     *   exams.teacher_id = auth()->id()
     * so a teacher only ever sees results for exams they personally authored.
     *
     * WHY exams.teacher_id and NOT courses.teacher_id:
     *   courses.teacher_id is mutable — admin can reassign a course to a new
     *   teacher. exams.teacher_id is an immutable snapshot set at exam-creation
     *   time, so a teacher retains historical visibility of all exams they
     *   authored even after a course is reassigned.
     *
     * Grouped by: Academic Year → Year Level → Semester → Course → Exam
     * Per-exam detail: one studentRow per attempt (full multi-attempt history),
     * summary counts based on the latest attempt only.
     *
     * This is a read-only presentation layer — no exam logic, timing, session
     * recovery, or anti-cheat behaviour is modified here.
     */
    public function index(Request $request)
    {
        $teacherId = auth()->id();

        // ── Load all academic years ───────────────────────────────────────
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        // ── Load courses that have at least one published/closed exam
        //    authored by this teacher.
        //    Both the whereHas constraint and the eager-loaded exams relationship
        //    filter on exams.teacher_id so no other teacher's data leaks through.
        $courses = Course::with([
            'academicYear',
            'enrollments',
            'exams' => fn($q) => $q
                ->whereIn('status', ['published', 'closed'])
                ->where('teacher_id', $teacherId)          // ← teacher scope (snapshot)
                ->with([
                    'latestSchedule',
                    'attempts' => fn($a) => $a->with([
                        'cheatingLogs' => fn($cl) => $cl->orderBy('warning_number'),
                    ]),
                    'results' => fn($r) => $r->with('student'),
                ]),
        ])
        ->whereHas('exams', fn($q) => $q
            ->whereIn('status', ['published', 'closed'])
            ->where('teacher_id', $teacherId)              // ← same scope on outer query
        )
        ->get();

        // ── Collect all enrolled student IDs across the teacher's courses ─
        $allEnrolledStudentIds = $courses
            ->flatMap(fn($c) => $c->enrollments->pluck('student_id'))
            ->unique();

        $enrolledStudents = User::whereIn('id', $allEnrolledStudentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        // ── Load student_year_records — the authoritative source ──────────
        // Map: student_id → ordered Collection<StudentYearRecord>
        $studentRecords = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->whereIn('student_id', $allEnrolledStudentIds)
            ->orderBy('academic_year_id')
            ->get()
            ->groupBy('student_id');

        // ── Build nested summary: [ayId][yearLevel][semester][courseId] ───
        //
        // For each student enrolled in a course, we find their StudentYearRecord
        // that matches the course's year_level and semester. That record's
        // academic_year_id is the correct grouping key.
        //
        // Match priority (identical to Admin):
        //   1. Exact match on both year_level and semester
        //   2. year_level matches, course covers both semesters (semester = 0)
        //   3. semester matches, course covers all year levels (year_level = 0)
        //   4. Most recent record (last resort fallback)
        $raw = [];

        foreach ($courses as $course) {
            $enrolledIds  = $course->enrollments->pluck('student_id')->unique();
            $courseYl     = (int) $course->year_level;   // 0 = all years
            $courseSemInt = (int) $course->semester;      // 0 = both semesters

            foreach ($course->exams as $exam) {
                $schedule = $exam->latestSchedule;

                // ── Index results by attempt_id for O(1) lookup ──────────
                $resultByAttemptId = $exam->results->keyBy('attempt_id');

                // ── Group attempts by student_id, sorted asc by attempt_number
                //    so attempt #1 → #2 → #3 renders in order.
                $attemptsByStudent = $exam->attempts
                    ->sortBy('attempt_number')
                    ->groupBy('student_id');

                foreach ($enrolledIds as $sid) {
                    $student = $enrolledStudents[$sid] ?? null;
                    if (! $student) continue;

                    $records = $studentRecords[$sid] ?? collect();

                    // ── Find the best matching StudentYearRecord ──────────
                    $record = null;

                    if ($records->isNotEmpty()) {
                        // Pass 1: exact match on both year_level and semester
                        if ($courseYl !== 0 && $courseSemInt !== 0) {
                            $record = $records->first(fn($r) =>
                                $r->yearLevel?->level === $courseYl
                                && (int) $r->semester === $courseSemInt
                            );
                        }
                        // Pass 2: year_level matches, course covers both semesters
                        if (! $record && $courseYl !== 0) {
                            $record = $records->first(fn($r) =>
                                $r->yearLevel?->level === $courseYl
                            );
                        }
                        // Pass 3: semester matches, course covers all year levels
                        if (! $record && $courseSemInt !== 0) {
                            $record = $records->first(fn($r) =>
                                (int) $r->semester === $courseSemInt
                            );
                        }
                        // Pass 4: fallback — most recent record
                        if (! $record) {
                            $record = $records->last();
                        }
                    }

                    // ── Skip student if they don't belong to this exam's cohort ──
                    if (! $record) continue;
                    if ($record->academic_year_id !== $exam->academic_year_id) continue;
                    if ($courseYl !== 0 && $record->yearLevel?->level !== $courseYl) continue;

                    // ── Grouping keys ─────────────────────────────────────
                    $ayId = $record->academic_year_id ?? ($course->academic_year_id ?? 0);
                    $yl   = $record->yearLevel?->level ?? ($courseYl ?: 0);
                    $sem  = $courseSemInt !== 0
                        ? $courseSemInt
                        : (int) ($record->semester ?? 0);

                    // ── Build one studentRow per attempt ─────────────────
                    // If no attempts exist for this student → single ABSENT row.
                    $studentAttempts = $attemptsByStudent[$sid] ?? collect();

                    if ($studentAttempts->isEmpty()) {
                        $studentRow = [
                            'student'       => $student,
                            'result'        => null,
                            'attempt'       => null,
                            'attemptNumber' => null,
                            'status'        => 'ABSENT',
                            'score'         => '—',
                            'percentage'    => null,
                            'violations'    => [],
                            'warningCount'  => 0,
                        ];

                        $raw[$ayId][$yl][$sem][$course->id]['course'] = $course;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['exam']     = $exam;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['schedule'] = $schedule;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['studentRows'][] = $studentRow;

                        $e = &$raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id];
                        $e['students'] = ($e['students'] ?? 0) + 1;
                        $e['absent']   = ($e['absent']   ?? 0) + 1;
                        unset($e);

                        continue;
                    }

                    // ── Latest attempt determines the summary counts ──────
                    // (Attempts are sorted asc, so last() = highest attempt_number.)
                    $latestAttempt = $studentAttempts->last();
                    $latestResult  = $resultByAttemptId[$latestAttempt->id] ?? null;
                    $latestStatus  = $latestResult
                        ? ($latestResult->exam_result_status ?? Result::STATUS_FAILED)
                        : 'ABSENT';

                    // ── One row per attempt for the detail table ──────────
                    foreach ($studentAttempts as $attempt) {
                        $result = $resultByAttemptId[$attempt->id] ?? null;

                        if ($result) {
                            $rowStatus  = $result->exam_result_status ?? Result::STATUS_FAILED;
                            $violations = $attempt->cheatingLogs
                                ->map(fn($cl) => $cl->violation_type)
                                ->unique()->values()->all();
                            $studentRow = [
                                'student'       => $student,
                                'result'        => $result,
                                'attempt'       => $attempt,
                                'attemptNumber' => $attempt->attempt_number,
                                'status'        => $rowStatus,
                                'score'         => $result->obtained_marks . '/' . $result->total_marks,
                                'percentage'    => $result->percentage,
                                'violations'    => $violations,
                                'warningCount'  => $attempt->warning_count ?? 0,
                            ];
                        } else {
                            $rowStatus  = 'ABSENT';
                            $studentRow = [
                                'student'       => $student,
                                'result'        => null,
                                'attempt'       => $attempt,
                                'attemptNumber' => $attempt->attempt_number,
                                'status'        => 'ABSENT',
                                'score'         => '—',
                                'percentage'    => null,
                                'violations'    => [],
                                'warningCount'  => $attempt->warning_count ?? 0,
                            ];
                        }

                        $raw[$ayId][$yl][$sem][$course->id]['course'] = $course;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['exam']      = $exam;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['schedule']  = $schedule;
                        $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['studentRows'][] = $studentRow;
                    }

                    // ── Summary counts — based on LATEST attempt only ─────
                    $e = &$raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id];
                    $e['students'] = ($e['students'] ?? 0) + 1;
                    $e['passed']   = ($e['passed']   ?? 0) + ($latestStatus === Result::STATUS_PASSED       ? 1 : 0);
                    $e['failed']   = ($e['failed']   ?? 0) + ($latestStatus === Result::STATUS_FAILED       ? 1 : 0);
                    $e['cheating'] = ($e['cheating'] ?? 0) + ($latestStatus === Result::STATUS_DISQUALIFIED ? 1 : 0);
                    $e['absent']   = ($e['absent']   ?? 0) + ($latestStatus === 'ABSENT'                    ? 1 : 0);
                    unset($e);
                }
            }
        }

        // ── Flatten to blade-ready shape ──────────────────────────────────
        // Blade iterates $summary[$ayId][$yl][$sem] as a plain array of
        // course-group entries. Convert and compute per-course totals here.
        $summary = [];
        foreach ($raw as $ayId => $ylGroups) {
            foreach ($ylGroups as $yl => $semGroups) {
                foreach ($semGroups as $sem => $courseGroups) {
                    foreach ($courseGroups as $courseId => $cg) {
                        $coursePassed = $courseFailed = $courseCheating = $courseAbsent = 0;
                        $examList = [];
                        foreach ($cg['exams'] as $es) {
                            $coursePassed   += $es['passed']   ?? 0;
                            $courseFailed   += $es['failed']   ?? 0;
                            $courseCheating += $es['cheating'] ?? 0;
                            $courseAbsent   += $es['absent']   ?? 0;
                            $examList[]      = $es;
                        }
                        // Unique students across all exams in this course group
                        $studentCount = collect($examList)
                            ->flatMap(fn($es) => collect($es['studentRows'])->pluck('student.id'))
                            ->unique()
                            ->count();

                        $summary[$ayId][$yl][$sem][] = [
                            'course'   => $cg['course'],
                            'exams'    => $examList,
                            'students' => $studentCount,
                            'passed'   => $coursePassed,
                            'failed'   => $courseFailed,
                            'cheating' => $courseCheating,
                            'absent'   => $courseAbsent,
                        ];
                    }
                }
            }
        }

        // ── Academic year lookup map for the blade ────────────────────────
        $ayMap = $academicYears->keyBy('id');

        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'result');

        return view('teacher.results.index', compact('summary', 'ayMap'));
    }
}

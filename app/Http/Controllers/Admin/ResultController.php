<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Result;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AcademicService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private AcademicService $academicService) {}

    /**
     * Admin: Academic Result Summary — grouped hierarchy.
     *
     * Loads all closed/published exams grouped by:
     *   Academic Year → Year Level → Semester → Course → Exam
     *
     * For each exam it collects:
     *   - enrolled students
     *   - result counts (passed / failed / disqualified)
     *   - absent students (enrolled but no result row)
     *
     * NOTHING about exam logic, timing, session recovery, or anti-cheat
     * is modified here. This is a read-only presentation layer.
     */
    public function index(Request $request)
    {
        // ── Load all academic years ───────────────────────────────────────
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        // ── Load courses that have at least one published/closed exam ─────
        $courses = Course::with([
            'academicYear',
            'enrollments',
            'exams' => fn($q) => $q->whereIn('status', ['published', 'closed'])
                ->with([
                    'latestSchedule',
                    'attempts' => fn($a) => $a->with([
                        'cheatingLogs' => fn($cl) => $cl->orderBy('warning_number'),
                    ]),
                    'results' => fn($r) => $r->with('student'),
                ]),
        ])
        ->whereHas('exams', fn($q) => $q->whereIn('status', ['published', 'closed']))
        ->get();

        // ── Collect all enrolled student IDs across all relevant courses ──
        $allEnrolledStudentIds = $courses
            ->flatMap(fn($c) => $c->enrollments->pluck('student_id'))
            ->unique();

        $enrolledStudents = User::whereIn('id', $allEnrolledStudentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        // ── Load student_year_records — the authoritative source ──────────
        // This tells us which academic year + year level + semester each
        // student actually belongs to. A student can have multiple records
        // (one per academic year they were enrolled in).
        //
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
        // academic_year_id is the correct grouping key — it tells us which
        // academic year the student was in when they took this course.
        //
        // This avoids all date-window heuristics and is robust to exam schedules
        // being set to arbitrary dates.
        //
        // Match priority for finding the student's record for a given course:
        //   1. Record whose year_level matches course.year_level AND semester
        //      matches course.semester (exact match on both)
        //   2. Record whose year_level matches (course semester = 0, any semester)
        //   3. Record whose semester matches (course year_level = 0, all years)
        //   4. Most recent record (last resort fallback)
        $raw = [];

        foreach ($courses as $course) {
            $enrolledIds  = $course->enrollments->pluck('student_id')->unique();
            $courseYl     = (int) $course->year_level;  // 0 = all years
            $courseSemInt = (int) $course->semester;     // 0 = both semesters

            foreach ($course->exams as $exam) {
                $schedule   = $exam->latestSchedule;
                $resultMap  = $exam->results->keyBy('student_id');
                $attemptMap = $exam->attempts->sortByDesc('id')->keyBy('student_id');

                foreach ($enrolledIds as $sid) {
                    $student = $enrolledStudents[$sid] ?? null;
                    if (! $student) continue;

                    $records = $studentRecords[$sid] ?? collect();

                    // ── Find the best matching StudentYearRecord ──────────
                    // We match by year_level and semester stored on the course,
                    // which is fixed metadata that never changes with reassignment.
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

                    // Grouping keys — come entirely from the matched enrollment record
                    $ayId = $record?->academic_year_id ?? ($course->academic_year_id ?? 0);
                    $yl   = $record?->yearLevel?->level ?? ($courseYl ?: 0);
                    // Display semester: prefer course's own semester tag (shows Sem 1 / Sem 2
                    // correctly); fall back to record semester when course is "both"
                    $sem  = $courseSemInt !== 0
                        ? $courseSemInt
                        : (int) ($record?->semester ?? 0);

                    // ── Build student result row ──────────────────────────
                    $result  = $resultMap[$sid]  ?? null;
                    $attempt = $attemptMap[$sid]  ?? null;

                    if ($result) {
                        $status     = $result->exam_result_status ?? \App\Models\Result::STATUS_FAILED;
                        $violations = $attempt
                            ? $attempt->cheatingLogs->map(fn($cl) => $cl->violation_type)->unique()->values()->all()
                            : [];
                        $studentRow = [
                            'student'      => $student,
                            'result'       => $result,
                            'status'       => $status,
                            'score'        => $result->obtained_marks . '/' . $result->total_marks,
                            'percentage'   => $result->percentage,
                            'violations'   => $violations,
                            'warningCount' => $attempt?->warning_count ?? 0,
                        ];
                    } else {
                        $status     = 'ABSENT';
                        $studentRow = [
                            'student'      => $student,
                            'result'       => null,
                            'status'       => 'ABSENT',
                            'score'        => '—',
                            'percentage'   => null,
                            'violations'   => [],
                            'warningCount' => 0,
                        ];
                    }

                    // ── Accumulate into hierarchy ─────────────────────────
                    $raw[$ayId][$yl][$sem][$course->id]['course'] = $course;
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['exam']      = $exam;
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['schedule']  = $schedule;
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['studentRows'][] = $studentRow;

                    // Running per-exam totals
                    $e = &$raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id];
                    $e['students'] = ($e['students'] ?? 0) + 1;
                    $e['passed']   = ($e['passed']   ?? 0) + ($status === \App\Models\Result::STATUS_PASSED       ? 1 : 0);
                    $e['failed']   = ($e['failed']   ?? 0) + ($status === \App\Models\Result::STATUS_FAILED       ? 1 : 0);
                    $e['cheating'] = ($e['cheating'] ?? 0) + ($status === \App\Models\Result::STATUS_DISQUALIFIED ? 1 : 0);
                    $e['absent']   = ($e['absent']   ?? 0) + ($status === 'ABSENT'                                ? 1 : 0);
                    unset($e);
                }
            }
        }

        // ── Flatten to the shape the blade expects ────────────────────────
        // Blade iterates $summary[$ayId][$yl][$sem] as a plain numeric array
        // of course-group entries. Convert and add per-course totals here.
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
                        // Count distinct students across all exams in this course group
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

        return view('admin.results.index', compact('summary', 'ayMap'));
    }

    /**
     * Admin: drill into one student's full result history.
     */
    public function student(User $student)
    {
        abort_unless($student->isStudent(), 404);

        $results  = Result::with(['exam.course', 'attempt'])
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        $history  = $this->academicService->getStudentHistory($student);

        return view('admin.results.student', compact('student', 'results', 'history'));
    }
}

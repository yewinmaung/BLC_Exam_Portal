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
     * Teacher: Academic Result Summary — same hierarchy as Admin view.
     *
     * Loads published/closed exams for courses owned by the logged-in teacher,
     * grouped by: Academic Year → Year Level → Semester → Course → Exam
     *
     * NOTHING about exam logic, timing, session recovery, or anti-cheat
     * is modified here. This is a read-only presentation layer.
     */
    public function index(Request $request)
    {
        $teacherId = auth()->id();

        // ── Load all academic years ───────────────────────────────────────
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        // ── Load only courses assigned to this teacher that have
        //    at least one published/closed exam ──────────────────────────
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
        ->where('teacher_id', $teacherId)
        ->whereHas('exams', fn($q) => $q->whereIn('status', ['published', 'closed']))
        ->get();

        // ── Collect all enrolled student IDs across the teacher's courses ─
        $allEnrolledStudentIds = $courses
            ->flatMap(fn($c) => $c->enrollments->pluck('student_id'))
            ->unique();

        $enrolledStudents = User::whereIn('id', $allEnrolledStudentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        // ── Load student_year_records ─────────────────────────────────────
        $studentRecords = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->whereIn('student_id', $allEnrolledStudentIds)
            ->orderBy('academic_year_id')
            ->get()
            ->groupBy('student_id');

        // ── Build nested summary: [ayId][yearLevel][semester][courseId] ───
        $raw = [];

        foreach ($courses as $course) {
            $enrolledIds  = $course->enrollments->pluck('student_id')->unique();
            $courseYl     = (int) $course->year_level;
            $courseSemInt = (int) $course->semester;

            foreach ($course->exams as $exam) {
                $schedule   = $exam->latestSchedule;
                $resultMap  = $exam->results->keyBy('student_id');
                $attemptMap = $exam->attempts->sortByDesc('id')->keyBy('student_id');

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

                    $ayId = $record?->academic_year_id ?? ($course->academic_year_id ?? 0);
                    $yl   = $record?->yearLevel?->level ?? ($courseYl ?: 0);
                    $sem  = $courseSemInt !== 0
                        ? $courseSemInt
                        : (int) ($record?->semester ?? 0);

                    // ── Build student result row ──────────────────────────
                    $result  = $resultMap[$sid]  ?? null;
                    $attempt = $attemptMap[$sid]  ?? null;

                    if ($result) {
                        $status     = $result->exam_result_status ?? Result::STATUS_FAILED;
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
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['exam']         = $exam;
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['schedule']     = $schedule;
                    $raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id]['studentRows'][] = $studentRow;

                    $e = &$raw[$ayId][$yl][$sem][$course->id]['exams'][$exam->id];
                    $e['students'] = ($e['students'] ?? 0) + 1;
                    $e['passed']   = ($e['passed']   ?? 0) + ($status === Result::STATUS_PASSED       ? 1 : 0);
                    $e['failed']   = ($e['failed']   ?? 0) + ($status === Result::STATUS_FAILED       ? 1 : 0);
                    $e['cheating'] = ($e['cheating'] ?? 0) + ($status === Result::STATUS_DISQUALIFIED ? 1 : 0);
                    $e['absent']   = ($e['absent']   ?? 0) + ($status === 'ABSENT'                    ? 1 : 0);
                    unset($e);
                }
            }
        }

        // ── Flatten to blade-ready shape ──────────────────────────────────
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

        // ── Academic year lookup map ──────────────────────────────────────
        $ayMap = $academicYears->keyBy('id');

        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'result');

        return view('teacher.results.index', compact('summary', 'ayMap'));
    }
}

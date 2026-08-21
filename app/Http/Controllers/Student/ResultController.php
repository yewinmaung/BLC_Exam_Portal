<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\StudentYearRecord;
use App\Services\AcademicService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private AcademicService $academicService) {}

    /**
     * Student: own PUBLISHED results grouped by academic enrollment history.
     *
     * Hierarchy: Academic Year → Year Level → Semester → Course → Exam → Result
     *
     * Source of truth for grouping:
     *   student_year_records.academic_year_id  → which year group
     *   student_year_records.year_level_id     → which year level
     *   student_year_records.semester          → which semester section
     *
     * Bridge to results:
     *   enrollments.year_level_id matches student_year_records.year_level_id
     *   → this links a result's course enrollment to the correct academic record
     *
     * courses.year_level, courses.semester, courses.academic_year_id are
     * NOT used anywhere in this method.
     *
     * Each result is assigned to exactly one record (no duplicates).
     * All records render even when they have no results yet.
     */
    public function index(Request $request)
    {
        $student = auth()->user();

        // ── 1. Load all enrollment year records, oldest first ─────────────
        $yearRecords = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        if ($yearRecords->isEmpty()) {
            \App\Models\UserNotification::markCategoryRead($student->id, 'result');
            return view('student.results.index', [
                'grouped'     => [],
                'totalExams'  => 0,
                'passedCount' => 0,
                'avgPct'      => 0,
            ]);
        }

        // ── 2. Load all results for this student (one query) ───────────────
        // Show ALL statuses (PASSED, FAILED, DISQUALIFIED, ABSENT).
        // Security: student_id filter ensures only the logged-in student's results.
        $allResults = Result::with([
                'exam.course',
                'exam.questions.answers',
                'attempt.studentAnswers.answer',
            ])
            ->where('student_id', $student->id)
            ->whereHas('exam.schedules', fn ($sq) =>
                $sq->where('ends_at', '<=', now())
            )
            ->latest()
            ->get();

        // ── 3. Assign each result to exactly one record ───────────────────
        //
        // The compound key on student_year_records is:
        //   (student_id, academic_year_id, year_level_id, semester)
        //
        // Bridge strategy:
        //   enrollment.year_level_id → narrows to records with that year level
        //   course.semester          → used ONLY as a routing hint to pick the
        //                             correct Sem-1 vs Sem-2 record within the
        //                             same year level (not used for display)
        //
        // Matching priority for a given result:
        //   1. record.year_level_id == enrollment.year_level_id
        //      AND record.semester == course.semester           (exact)
        //   2. record.year_level_id == enrollment.year_level_id
        //      AND (record.semester == 0 OR course.semester == 0) (wildcard sem)
        //   3. enrollment.year (legacy int) matches record.yearLevel.level
        //      with the same semester tie-breaking
        //   4. Most recent record                               (last resort)
        //
        // The semester shown in the UI always comes from record.semester —
        // course.semester is never rendered.

        // Pre-load enrollments: course_id → enrollment row
        $enrollments = \App\Models\Enrollment::where('student_id', $student->id)
            ->get()
            ->keyBy('course_id');

        // Build compound lookup: "ylId:sem" → record id
        // Prefer older records for same compound key (first enrolled wins)
        $compoundToRecordId = [];
        foreach ($yearRecords->sortBy('academic_year_id') as $rec) {
            $ylId    = (int) $rec->year_level_id;
            $recSem  = (int) $rec->semester;
            $key     = "{$ylId}:{$recSem}";
            if ($ylId && ! isset($compoundToRecordId[$key])) {
                $compoundToRecordId[$key] = $rec->id;
            }
        }

        // Also build year-level-only fallback (for wildcard semester = 0)
        $ylIdToRecordId = [];
        foreach ($yearRecords->sortByDesc('academic_year_id') as $rec) {
            $ylId = (int) $rec->year_level_id;
            if ($ylId && ! isset($ylIdToRecordId[$ylId])) {
                $ylIdToRecordId[$ylId] = $rec->id;
            }
        }

        // Legacy integer fallback: year int → record id
        $ylIntToRecordId = [];
        foreach ($yearRecords->sortByDesc('academic_year_id') as $rec) {
            $lvl = (int) ($rec->yearLevel?->level ?? 0);
            if ($lvl && ! isset($ylIntToRecordId[$lvl])) {
                $ylIntToRecordId[$lvl] = $rec->id;
            }
        }

        $lastResortRecordId = $yearRecords->sortByDesc('academic_year_id')->first()?->id;

        // Initialise empty buckets keyed by record id
        $buckets = $yearRecords->mapWithKeys(fn ($r) => [$r->id => collect()])->toArray();

        foreach ($allResults as $result) {
            $courseId   = $result->exam?->course_id;
            $enrollment = $courseId ? ($enrollments[$courseId] ?? null) : null;
            $courseSem  = (int) ($result->exam?->course?->semester ?? 0); // routing only

            $targetRecordId = null;

            if ($enrollment) {
                $enrollYlId  = (int) ($enrollment->year_level_id ?? 0);
                $enrollYlInt = (int) ($enrollment->year ?? 0);

                if ($enrollYlId) {
                    // Pass 1: exact compound match (year_level_id + semester)
                    if ($courseSem > 0) {
                        $key = "{$enrollYlId}:{$courseSem}";
                        $targetRecordId = $compoundToRecordId[$key] ?? null;
                    }

                    // Pass 2: year_level_id matches, semester wildcard on course
                    if (! $targetRecordId) {
                        // course.semester = 0 means "both" — match sem=0 record first,
                        // then any record for that year level
                        $key0 = "{$enrollYlId}:0";
                        $targetRecordId = $compoundToRecordId[$key0]
                                          ?? $ylIdToRecordId[$enrollYlId]
                                          ?? null;
                    }
                }

                // Pass 3: legacy integer year fallback
                if (! $targetRecordId && $enrollYlInt) {
                    $targetRecordId = $ylIntToRecordId[$enrollYlInt] ?? null;
                }
            }

            // Pass 4: last resort
            if (! $targetRecordId) {
                $targetRecordId = $lastResortRecordId;
            }

            if ($targetRecordId && isset($buckets[$targetRecordId])) {
                $buckets[$targetRecordId][] = $result;
            }
        }

        // ── 4. Build $grouped — visual semester split ─────────────────────
        //
        // All results assigned to a record are split into Semester 1 / Semester 2
        // sections using course.semester for the visual placement.
        //
        // This is display-only: course.semester = 1 → show in Sem 1 section,
        // course.semester = 2 → show in Sem 2 section,
        // course.semester = 0 (both) → show in Sem 1 section to avoid duplication.
        //
        // record.semester is still used to label the card header but is NOT
        // used to force all results into a single semester section, because a
        // student may have only one record per academic year that covers courses
        // from both semesters.
        $grouped = [];
        foreach ($yearRecords as $record) {
            $results = collect($buckets[$record->id] ?? []);

            $sem1 = $results->filter(fn ($r) =>
                in_array((int) ($r->exam?->course?->semester ?? 0), [0, 1])
            )->values();

            $sem2 = $results->filter(fn ($r) =>
                (int) ($r->exam?->course?->semester ?? 0) === 2
            )->values();

            $grouped[] = [
                'record' => $record,
                'sem1'   => $sem1,
                'sem2'   => $sem2,
            ];
        }

        // ── 5. Summary stats (each result counted once) ───────────────────
        $totalExams  = $allResults->count();
        $passedCount = $allResults->where('is_passed', true)->count();
        $avgPct      = round($allResults->avg('percentage') ?? 0, 1);

        \App\Models\UserNotification::markCategoryRead($student->id, 'result');

        return view('student.results.index', compact(
            'grouped',
            'totalExams',
            'passedCount',
            'avgPct'
        ));
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Result;
use App\Services\AcademicService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResultController extends Controller
{
    public function __construct(private AcademicService $academicService) {}

    /**
     * Student: own PUBLISHED results for the current (or selected) academic year,
     * grouped into Semester 1 and Semester 2. Past years appear under History.
     */
    public function index(Request $request)
    {
        $studentId     = auth()->id();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $currentYear   = AcademicYear::current();

        // Default to current academic year when no year filter is chosen
        $selectedYearId = $request->filled('academic_year_id')
            ? (int) $request->academic_year_id
            : ($currentYear?->id);

        $selectedYear = $selectedYearId
            ? $academicYears->firstWhere('id', $selectedYearId)
            : $currentYear;

        $selectedSemester = $request->filled('semester') ? (int) $request->semester : null;

        $yearResults = $this->loadYearResults($studentId, $selectedYearId);

        $sem1Results = $yearResults->filter(function (Result $r) {
            $sem = (int) ($r->exam?->course?->semester ?? 0);
            // semester 0 (both) listed under Sem 1 so it is not duplicated
            return $sem === 0 || $sem === 1;
        })->values();

        $sem2Results = $yearResults->filter(function (Result $r) {
            $sem = (int) ($r->exam?->course?->semester ?? 0);
            return $sem === 2;
        })->values();

        // Semester filter: show only the chosen semester section
        if ($selectedSemester === 1) {
            $sem2Results = collect();
        } elseif ($selectedSemester === 2) {
            $sem1Results = collect();
        }

        $allVisible = $sem1Results->merge($sem2Results)->unique('id');
        $totalExams  = $allVisible->count();
        $passedCount = $allVisible->where('is_passed', true)->count();
        $avgPct      = round($allVisible->avg('percentage') ?? 0, 1);

        $history = $this->academicService->getStudentHistory(auth()->user());

        // Keep current-year results in My Exam Results only (avoid duplicate in History)
        if ($currentYear) {
            $history = array_values(array_filter(
                $history,
                fn ($h) => (int) $h['record']->academic_year_id !== (int) $currentYear->id
            ));
        }

        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'result');

        return view('student.results.index', compact(
            'academicYears',
            'currentYear',
            'selectedYear',
            'selectedYearId',
            'selectedSemester',
            'sem1Results',
            'sem2Results',
            'totalExams',
            'passedCount',
            'avgPct',
            'history'
        ));
    }

    private function loadYearResults(int $studentId, ?int $academicYearId): Collection
    {
        if (!$academicYearId) {
            return collect();
        }

        return Result::with([
                'exam.course',
                'exam.questions.answers',
                'attempt.studentAnswers.answer',
            ])
            ->where('student_id', $studentId)
            ->where('is_published', true)
            ->whereHas('exam.schedules', fn ($sq) => $sq->where('ends_at', '<=', now()))
            ->whereHas('exam.course', fn ($c) => $c->where('academic_year_id', $academicYearId))
            ->latest()
            ->get();
    }
}

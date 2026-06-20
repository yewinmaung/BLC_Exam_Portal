<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Result;
use App\Services\TranscriptService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private TranscriptService $transcriptService) {}

    /**
     * Student: own PUBLISHED results only, for exams whose schedule has ENDED.
     * Results for exams still in-progress (schedule not yet finished) are hidden.
     */
    public function index(Request $request)
    {
        $studentId     = auth()->id();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        $query = Result::with(['exam.course'])
            ->where('student_id', $studentId)
            ->where('is_published', true)
            // Only show results for exams whose schedule has already ended
            ->whereHas('exam.schedules', function ($sq) {
                $sq->where('ends_at', '<=', now());
            })
            ->latest();

        if ($request->filled('academic_year_id')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->from('student_year_records')
                    ->where('student_year_records.student_id', auth()->id())
                    ->where('student_year_records.academic_year_id', (int) $request->academic_year_id);
            });
        }

        if ($request->filled('semester')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->from('student_year_records')
                    ->where('student_year_records.student_id', auth()->id())
                    ->where('student_year_records.semester', $request->semester);
            });
        }

        $results  = $query->paginate(20)->withQueryString();
        $history  = $this->transcriptService->getHistory(auth()->user());

        return view('student.results.index', compact('results', 'academicYears', 'history'));
    }
}

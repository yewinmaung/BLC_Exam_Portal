<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Result;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AcademicService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private AcademicService $academicService) {}

    /**
     * Admin: all students' results with rich filters.
     */
    public function index(Request $request)
    {
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $students      = User::whereHas('role', fn($q) => $q->where('slug', RoleSlug::STUDENT))
                            ->where('is_active', true)->orderBy('name')->get(['id','name','email']);

        $query = Result::with(['student', 'exam.course'])->latest();

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->student_id);
        }
        if ($request->filled('course_id')) {
            $query->whereHas('exam', fn($q) => $q->where('course_id', (int) $request->course_id));
        }
        if ($request->filled('exam_id')) {
            $query->where('exam_id', (int) $request->exam_id);
        }
        if ($request->filled('is_passed')) {
            $query->where('is_passed', (bool) $request->is_passed);
        }
        if ($request->filled('academic_year_id')) {
            $query->whereExists(fn($sub) => $sub
                ->from('student_year_records')
                ->whereColumn('student_year_records.student_id', 'results.student_id')
                ->where('student_year_records.academic_year_id', (int) $request->academic_year_id)
            );
        }
        if ($request->filled('year_level_id')) {
            $query->whereExists(fn($sub) => $sub
                ->from('student_year_records')
                ->whereColumn('student_year_records.student_id', 'results.student_id')
                ->where('student_year_records.year_level_id', (int) $request->year_level_id)
            );
        }
        if ($request->filled('semester')) {
            $query->whereExists(fn($sub) => $sub
                ->from('student_year_records')
                ->whereColumn('student_year_records.student_id', 'results.student_id')
                ->where('student_year_records.semester', $request->semester)
            );
        }

        // Collect stats before pagination (count queries, no data loaded into memory)
        $stats = [
            'total'   => (clone $query)->count(),
            'passed'  => (clone $query)->where('is_passed', true)->count(),
            'failed'  => (clone $query)->where('is_passed', false)->count(),
            'avg_pct' => round((clone $query)->avg('percentage') ?? 0, 1),
        ];

        $results  = $query->paginate(25)->withQueryString();

        return view('admin.results.index', compact(
            'results', 'academicYears', 'yearLevels', 'courses', 'students', 'stats'
        ));
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

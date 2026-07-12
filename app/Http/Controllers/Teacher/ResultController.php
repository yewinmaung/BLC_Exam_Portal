<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Result;
use App\Models\YearLevel;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    /**
     * Teacher: results for all exams they own, with filters.
     */
    public function index(Request $request)
    {
        $teacherId     = auth()->id();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $courses       = Course::where('teacher_id', $teacherId)
                            ->where('is_active', true)->orderBy('title')->get();

        $query = Result::with(['student', 'exam.course'])
            ->whereHas('exam', fn($q) => $q->where('teacher_id', $teacherId))
            ->latest();

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
            $query->whereExists(function ($sub) use ($request) {
                $sub->from('student_year_records')
                    ->whereColumn('student_year_records.student_id', 'results.student_id')
                    ->where('student_year_records.academic_year_id', (int) $request->academic_year_id);
            });
        }

        if ($request->filled('year_level_id')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->from('student_year_records')
                    ->whereColumn('student_year_records.student_id', 'results.student_id')
                    ->where('student_year_records.year_level_id', (int) $request->year_level_id);
            });
        }

        if ($request->filled('semester')) {
            $query->whereExists(fn($sub) => $sub
                ->from('student_year_records')
                ->whereColumn('student_year_records.student_id', 'results.student_id')
                ->where('student_year_records.semester', $request->semester)
            );
        }

        $results = $query->paginate(25)->withQueryString();

        // Mark result notifications as read when teacher opens Results page
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'result');

        return view('teacher.results.index', compact(
            'results', 'academicYears', 'yearLevels', 'courses'
        ));
    }
}

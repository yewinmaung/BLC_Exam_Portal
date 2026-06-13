<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Enums\RoleSlug;
use App\Services\CourseAssignmentService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseAssignmentService $courseAssignment)
    {
    }

    public function index()
    {
        $courses = Course::with(['teacher', 'enrollments'])->latest()->paginate(15);
        return view('admin.courses.index', compact('courses'));
    }

    public function create()
    {
        $teachers    = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))->get();
        $yearLevels  = Course::$yearLevelLabels;
        $academicYears = \App\Models\AcademicYear::orderByDesc('start_year')->get();
        return view('admin.courses.create', compact('teachers', 'yearLevels', 'academicYears'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'code'             => 'required|string|unique:courses,code',
            'description'      => 'nullable|string',
            'teacher_id'       => 'nullable|exists:users,id',
            'year_level'       => 'required|integer|min:0|max:5',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester'         => 'required|integer|min:0|max:2',
        ]);

        Course::create([...$data, 'created_by' => auth()->id()]);
        return redirect()->route('admin.courses.index')->with('success', 'Course created.');
    }

    public function edit(Course $course)
    {
        $teachers      = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))->get();
        $students      = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))->get();
        $enrolledIds   = $course->enrollments()->pluck('student_id');
        $yearLevels    = Course::$yearLevelLabels;
        $academicYears = \App\Models\AcademicYear::orderByDesc('start_year')->get();
        return view('admin.courses.edit', compact('course', 'teachers', 'students', 'enrolledIds', 'yearLevels', 'academicYears'));
    }

    public function update(Request $request, Course $course)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'code'             => 'required|string|unique:courses,code,' . $course->id,
            'description'      => 'nullable|string',
            'teacher_id'       => 'nullable|exists:users,id',
            'is_active'        => 'boolean',
            'year_level'       => 'required|integer|min:0|max:5',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester'         => 'required|integer|min:0|max:2',
            'student_ids'      => 'nullable|array',
            'student_ids.*'    => 'exists:users,id',
        ]);

        $course->update([
            'title'            => $data['title'],
            'code'             => $data['code'],
            'description'      => $data['description'] ?? null,
            'teacher_id'       => $data['teacher_id'] ?? null,
            'is_active'        => $request->boolean('is_active', true),
            'year_level'       => $data['year_level'],
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'semester'         => $data['semester'],
        ]);

        $this->courseAssignment->syncCourseStudents($course, $request->input('student_ids', []));
        return redirect()->route('admin.courses.index')->with('success', 'Course updated.');
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    /**
     * AJAX: return courses filtered by academic_year_id, year_level, semester.
     * Used by student create/edit dynamic filtering.
     */
    public function byYearLevel(Request $request)
    {
        $yearLevel      = (int) $request->get('year_level', 0);
        $academicYearId = (int) $request->get('academic_year_id', 0);
        $semester       = (int) $request->get('semester', 0);

        $courses = Course::where('is_active', true)
            ->when($yearLevel > 0, fn ($q) => $q->where(function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel)->orWhere('year_level', 0);
            }))
            ->when($academicYearId > 0, fn ($q) => $q->where(function ($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId)->orWhereNull('academic_year_id');
            }))
            ->when($semester > 0, fn ($q) => $q->where(function ($q) use ($semester) {
                $q->where('semester', $semester)->orWhere('semester', 0);
            }))
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'year_level', 'semester', 'academic_year_id']);

        return response()->json($courses);
    }
}

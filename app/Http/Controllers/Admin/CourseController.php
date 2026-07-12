<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Major;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\CourseAssignmentService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseAssignmentService $courseAssignment)
    {
    }

    // ── Index ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search         = $request->string('search')->trim()->limit(100)->value();
        $yearLevel      = $request->filled('year_level')      ? (int)    $request->year_level      : null;
        $academicYearId = $request->filled('academic_year_id') ? (int)    $request->academic_year_id : null;
        $majorId        = $request->filled('major_id')        ? (int)    $request->major_id         : null;
        $status         = $request->filled('status')          ?          $request->status           : null;

        // Data for filter dropdowns
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();
        $yearLevels    = Course::$yearLevelLabels;

        $courses = Course::with(['teacher', 'enrollments', 'academicYear', 'major'])
            ->when($search, fn ($q) =>
                $q->where(fn ($s) =>
                    $s->where('title', 'like', "%{$search}%")
                      ->orWhere('code',  'like', "%{$search}%")
                )
            )
            ->when($yearLevel !== null, fn ($q) => $q->where('year_level', $yearLevel))
            ->when($academicYearId,     fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($majorId,            fn ($q) => $q->where('major_id', $majorId))
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Auto-mark all unread 'course' category notifications as read on page visit
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'course');

        return view('admin.courses.index', compact(
            'courses', 'search',
            'academicYears', 'majors', 'yearLevels',
            'yearLevel', 'academicYearId', 'majorId', 'status'
        ));
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function create()
    {
        Major::ensureDefaults();

        $teachers      = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))
            ->orderBy('name')->get();
        $yearLevels    = Course::$yearLevelLabels;
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $currentYearId = $academicYears->firstWhere('is_current', true)?->id;
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

        return view('admin.courses.create', compact('teachers', 'yearLevels', 'academicYears', 'currentYearId', 'majors'));
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'code'             => 'required|string|unique:courses,code',
            'description'      => 'nullable|string',
            'teacher_id'       => 'required|exists:users,id',   // teacher is now REQUIRED
            'year_level'       => 'required|integer|min:0|max:5',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester'         => 'required|integer|min:1|max:2',
            'major_id'         => 'required|exists:majors,id',
        ]);

        Course::create([...$data, 'created_by' => auth()->id()]);

        return redirect()->route('admin.courses.index')->with('success', 'Course created.');
    }

    // ── Edit ──────────────────────────────────────────────────────────────

    public function edit(Course $course)
    {
        Major::ensureDefaults();

        $teachers      = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))
            ->orderBy('name')->get();
        $students      = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->orderBy('name')->get();
        $enrolledIds   = $course->enrollments()->pluck('student_id');
        $yearLevels    = Course::$yearLevelLabels;
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

        return view('admin.courses.edit', compact(
            'course', 'teachers', 'students', 'enrolledIds', 'yearLevels', 'academicYears', 'majors'
        ));
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(Request $request, Course $course)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'code'             => 'required|string|unique:courses,code,' . $course->id,
            'description'      => 'nullable|string',
            'teacher_id'       => 'required|exists:users,id',
            'is_active'        => 'boolean',
            'year_level'       => 'required|integer|min:0|max:5',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester'         => 'required|integer|min:0|max:2',
            'major_id'         => 'required|exists:majors,id',
            'student_ids'      => 'nullable|array',
            'student_ids.*'    => 'exists:users,id',
        ]);

        $course->update([
            'title'            => $data['title'],
            'code'             => $data['code'],
            'description'      => $data['description'] ?? null,
            'teacher_id'       => $data['teacher_id'],
            'is_active'        => $request->boolean('is_active', true),
            'year_level'       => $data['year_level'],
            'academic_year_id' => $data['academic_year_id'],
            'semester'         => $data['semester'],
            'major_id'         => $data['major_id'],
        ]);

        $this->courseAssignment->syncCourseStudents($course, $request->input('student_ids', []));

        return redirect()->route('admin.courses.index')->with('success', 'Course updated.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────

    public function destroy(Course $course)
    {
        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    // ── AJAX: courses filtered by year_level / semester / academic_year / major ──

    public function byYearLevel(Request $request)
    {
        $yearLevel      = (int) $request->get('year_level', 0);
        $academicYearId = (int) $request->get('academic_year_id', 0);
        $semester       = (int) $request->get('semester', 0);
        $majorId        = (int) $request->get('major_id', 0);

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
            ->when($majorId > 0, fn ($q) => $q->where('major_id', $majorId))
            ->orderBy('title')
            ->with('major')
            ->get(['id', 'title', 'code', 'year_level', 'semester', 'academic_year_id', 'major_id']);

        return response()->json($courses);
    }
}

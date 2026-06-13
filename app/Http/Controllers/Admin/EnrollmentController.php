<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    private static array $yearLabels = [
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
        5 => 'Final Year',
    ];

    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $years         = self::$yearLabels;
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();

        // For the filter bar only — all students
        $students = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        $query = Enrollment::with(['student', 'course'])->latest();

        if ($request->filled('course_id'))  { $query->where('course_id',  $request->course_id); }
        if ($request->filled('year'))       { $query->where('year',        $request->year); }
        if ($request->filled('student_id')) { $query->where('student_id',  $request->student_id); }

        $enrollments = $query->paginate(20)->withQueryString();

        return view('admin.enrollments.index', compact(
            'enrollments', 'courses', 'students', 'years', 'academicYears', 'yearLevels'
        ));
    }

    /**
     * AJAX: Return students filtered by academic_year_id + year_level_id.
     * Used by the enrollment form's dynamic student list.
     */
    public function studentsByYearLevel(Request $request)
    {
        $academicYearId = (int) $request->get('academic_year_id', 0);
        $yearLevelId    = (int) $request->get('year_level_id', 0);

        if (!$academicYearId || !$yearLevelId) {
            return response()->json([]);
        }

        // Find students who have a StudentYearRecord for this academic year + year level
        $studentIds = StudentYearRecord::where('academic_year_id', $academicYearId)
            ->where('year_level_id', $yearLevelId)
            ->pluck('student_id');

        $students = User::whereIn('id', $studentIds)
            ->whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'student_ids'      => 'required|array|min:1',
            'student_ids.*'    => 'exists:users,id',
            'year'             => 'required|integer|min:1|max:5',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'year_level_id'    => 'nullable|exists:year_levels,id',
            'semester'         => 'nullable|in:1,2',
        ]);

        $course    = Course::findOrFail($data['course_id']);
        $yearLabel = self::$yearLabels[$data['year']] ?? 'Year ' . $data['year'];

        // Backend validation: ensure course matches selected academic year / year level / semester
        if (!empty($data['academic_year_id']) && $course->academic_year_id
            && $course->academic_year_id != $data['academic_year_id']) {
            return back()->withErrors(['course_id' => 'Selected course does not belong to the chosen academic year.'])->withInput();
        }

        if (!empty($data['year_level_id'])) {
            $yearLevel = YearLevel::find($data['year_level_id']);
            if ($yearLevel && $course->year_level > 0 && $course->year_level != $yearLevel->level) {
                return back()->withErrors(['course_id' => 'Selected course does not match the chosen year level.'])->withInput();
            }
        }

        if (!empty($data['semester']) && $course->semester > 0 && $course->semester != $data['semester']) {
            return back()->withErrors(['course_id' => 'Selected course does not match the chosen semester.'])->withInput();
        }

        $created = 0;
        $skipped = 0;

        foreach ($data['student_ids'] as $studentId) {
            // Validate: student must belong to the selected academic year + year level
            if (!empty($data['academic_year_id']) && !empty($data['year_level_id'])) {
                $validRecord = StudentYearRecord::where('student_id', $studentId)
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('year_level_id', $data['year_level_id'])
                    ->exists();

                if (!$validRecord) {
                    $skipped++;
                    continue;
                }
            }

            if (Enrollment::where([
                'course_id'  => $data['course_id'],
                'student_id' => $studentId,
                'year'       => $data['year'],
            ])->exists()) {
                $skipped++;
                continue;
            }

            Enrollment::create([
                'course_id'   => $data['course_id'],
                'student_id'  => $studentId,
                'year'        => $data['year'],
                'enrolled_at' => now(),
            ]);

            $student = User::find($studentId);
            if ($student) {
                $this->notifications->notify(
                    $student, 'enrolled', 'Enrolled in Course',
                    "You have been enrolled in \"{$course->title}\" for {$yearLabel}.",
                    route('student.courses.index')
                );
            }
            $created++;
        }

        $msg = "{$created} student(s) enrolled in {$yearLabel}.";
        if ($skipped > 0) $msg .= " {$skipped} already enrolled or invalid (skipped).";

        return back()->with('success', $msg);
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();
        return back()->with('success', 'Enrollment removed.');
    }
}

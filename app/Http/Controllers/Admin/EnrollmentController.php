<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Major;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    // ── Index ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        YearLevel::ensureDefaults();
        Major::ensureDefaults();

        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $currentYearId = $academicYears->firstWhere('is_current', true)?->id;
        $yearLevels    = YearLevel::orderBy('level')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

        // CS and CT students take CST courses — pass CST major ID to the view
        // so the JS can substitute it when CS or CT is selected.
        $cstMajorId = Major::where('code', 'CST')->value('id');
        $csMajorId  = Major::where('code', 'CS')->value('id');
        $ctMajorId  = Major::where('code', 'CT')->value('id');

        $students = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        $query = Enrollment::with(['student', 'course', 'yearLevel', 'major'])->latest();

        if ($request->filled('course_id'))    { $query->where('course_id',    $request->course_id); }
        if ($request->filled('year_level_id')){ $query->where('year_level_id', $request->year_level_id); }
        if ($request->filled('major_id'))     { $query->where('major_id',      $request->major_id); }
        if ($request->filled('student_id'))   { $query->where('student_id',    $request->student_id); }

        // Search: match student name/email or course title/code
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->limit(100)->value();
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', fn ($s) =>
                    $s->where('name',  'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                )->orWhereHas('course', fn ($c) =>
                    $c->where('title', 'like', "%{$search}%")
                      ->orWhere('code',  'like', "%{$search}%")
                );
            });
        }

        $enrollments = $query->paginate(5)->withQueryString();

        // Enrollments page = same 'course' category as Courses page
        // (notification type 'enrolled' maps to 'course')
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'course');

        return view('admin.enrollments.index', compact(
            'enrollments', 'courses', 'students', 'academicYears', 'currentYearId',
            'yearLevels', 'majors', 'cstMajorId', 'csMajorId', 'ctMajorId'
        ));
    }

    // ── AJAX: students filtered by academic_year_id + year_level_id + major_id ──

    public function studentsByYearLevel(Request $request)
    {
        $academicYearId = (int) $request->get('academic_year_id', 0);
        $yearLevelId    = (int) $request->get('year_level_id', 0);
        $majorId        = (int) $request->get('major_id', 0);
        $semester       = $request->get('semester', ''); // '1', '2', or ''

        if (!$academicYearId || !$yearLevelId) {
            return response()->json([]);
        }

        $yearLevel = YearLevel::find($yearLevelId);
        if (! $yearLevel) {
            return response()->json([]);
        }

        // Base: students with a StudentYearRecord for this academic year + year level
        $recordQuery = StudentYearRecord::where('academic_year_id', $academicYearId)
            ->where('year_level_id', $yearLevelId);

        // Filter by semester when one is selected (StudentYearRecord.semester stores '1' or '2')
        if ($semester !== '' && in_array($semester, ['1', '2'], true)) {
            $recordQuery->where('semester', $semester);
        }

        // Year 1 → no major filter (all Year 1 students are CST)
        // Year 2+ → filter by major name string when a major_id is supplied
        // NOTE: StudentYearRecord.major stores the full major name (e.g. "Computer Technology"),
        //       not the short code. Match against majors.name.
        if ($yearLevel->level >= 2 && $majorId > 0) {
            $major = Major::find($majorId);
            if ($major) {
                $recordQuery->where('major', $major->name);
            }
        }

        $studentIds = $recordQuery->pluck('student_id');

        $students = User::whereIn('id', $studentIds)
            ->whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($students);
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_ids'       => 'required|array|min:1',
            'course_ids.*'     => 'exists:courses,id',
            'student_ids'      => 'required|array|min:1',
            'student_ids.*'    => 'exists:users,id',
            'year_level_id'    => 'required|exists:year_levels,id',
            'major_id'         => 'nullable|exists:majors,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester'         => 'nullable|in:1,2',
        ]);

        $yearLevel = YearLevel::findOrFail($data['year_level_id']);
        $created   = 0;
        $skipped   = 0;
        $skipReasons = []; // Track reasons for debugging

        // ── Backend: validate student belongs to the selected year level + major ──
        // StudentYearRecord.major stores the full major name (e.g. "Computer Technology"),
        // not the short code. Resolve from majors.name using the submitted major_id.
        $submittedMajorName = null;
        if ($yearLevel->level >= 2 && !empty($data['major_id'])) {
            $submittedMajorName = Major::where('id', $data['major_id'])->value('name');
        }

        $invalidStudents = [];
        foreach ($data['student_ids'] as $studentId) {
            $recordQuery = StudentYearRecord::where('student_id', $studentId)
                ->where('year_level_id', $data['year_level_id']);

            if (!empty($data['academic_year_id'])) {
                $recordQuery->where('academic_year_id', $data['academic_year_id']);
            }

            // For Year 2+, verify the student's record has the matching major name
            if ($submittedMajorName) {
                $recordQuery->where('major', $submittedMajorName);
            }

            if (! $recordQuery->exists()) {
                $student = User::find($studentId);
                $invalidStudents[] = $student?->name ?? "Student #{$studentId}";
            }
        }

        if (! empty($invalidStudents)) {
            return back()
                ->withInput()
                ->withErrors(['student_ids' => 'The following students do not belong to the selected Year Level/Major: ' . implode(', ', $invalidStudents)]);
        }

        foreach ($data['course_ids'] as $courseId) {
            $course = Course::with('major')->findOrFail($courseId);

            // ── Backend scope validation per course ──────────────────────

            // 1. Course year_level must match (0 = all years allowed)
            if ($course->year_level > 0 && $course->year_level !== $yearLevel->level) {
                $skipped++;
                $skipReasons[] = "Course '{$course->code}': Year level mismatch (course requires year {$course->year_level}, selected {$yearLevel->level})";
                continue;
            }

            // 2. Academic year must match if course is restricted
            if (!empty($data['academic_year_id']) && $course->academic_year_id
                && $course->academic_year_id != $data['academic_year_id']) {
                $skipped++;
                $skipReasons[] = "Course '{$course->code}': Academic year mismatch";
                continue;
            }

            // 3. Semester must match if course is restricted (0 = both)
            if (!empty($data['semester']) && $course->semester > 0 && $course->semester != $data['semester']) {
                $skipped++;
                $skipReasons[] = "Course '{$course->code}': Semester mismatch (course is semester {$course->semester}, selected {$data['semester']})";
                continue;
            }

            // 4. Major validation
            if ($course->major_id) {
                if ($yearLevel->level === 1) {
                    // Year 1: only CST courses are allowed.
                    // Accept if:
                    //   a) no major submitted (admin left it blank), OR
                    //   b) submitted major matches the course's major (CST)
                    $cstMajor = Major::where('code', 'CST')->first();
                    $isCstCourse = $cstMajor && (int)$course->major_id === (int)$cstMajor->id;

                    if (!$isCstCourse) {
                        $skipped++;
                        $skipReasons[] = "Course '{$course->code}': Only CST courses allowed for Year 1";
                        continue;
                    }
                    // CST course + Year 1 → always allow regardless of submitted major_id
                } else {
                    // Year 2+: submitted major must exactly match the course's major.
                    // Special case: CS and CT students take CST courses.
                    $cstMajor = Major::where('code', 'CST')->first();
                    $csMajor  = Major::where('code', 'CS')->first();
                    $ctMajor  = Major::where('code', 'CT')->first();

                    $submittedMajorId = (int) ($data['major_id'] ?? 0);
                    $courseMajorId    = (int) $course->major_id;

                    $isMatch = $submittedMajorId === $courseMajorId;

                    // CS or CT student enrolling in a CST course → allowed
                    $isCsOrCt  = $csMajor && $ctMajor
                        && in_array($submittedMajorId, [(int)$csMajor->id, (int)$ctMajor->id], true);
                    $isCstCourse = $cstMajor && $courseMajorId === (int)$cstMajor->id;

                    if (!$isMatch && !($isCsOrCt && $isCstCourse)) {
                        $skipped++;
                        $skipReasons[] = "Course '{$course->code}': Major mismatch (course requires major ID {$course->major_id}, selected " . ($data['major_id'] ?? 'none') . ")";
                        continue;
                    }
                }
            }

            foreach ($data['student_ids'] as $studentId) {
                // Student must have a valid StudentYearRecord for this academic year + year level
                if (!empty($data['academic_year_id'])) {
                    $validRecord = StudentYearRecord::where('student_id', $studentId)
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->where('year_level_id', $data['year_level_id'])
                        ->exists();

                    if (!$validRecord) {
                        $skipped++;
                        $student = User::find($studentId);
                        $skipReasons[] = "Student '{$student->name}' for course '{$course->code}': No valid year record";
                        continue;
                    }
                }

                // Skip duplicate enrollment for the same course + student + year_level
                $exists = Enrollment::where([
                    'course_id'     => $courseId,
                    'student_id'    => $studentId,
                    'year_level_id' => $data['year_level_id'],
                ])->exists();

                if ($exists) {
                    $skipped++;
                    $student = User::find($studentId);
                    $skipReasons[] = "Student '{$student->name}' for course '{$course->code}': Already enrolled";
                    continue;
                }

                Enrollment::create([
                    'course_id'     => $courseId,
                    'student_id'    => $studentId,
                    'year'          => $yearLevel->level,
                    'year_level_id' => $data['year_level_id'],
                    'major_id'      => $course->major_id ?? $data['major_id'],
                    'enrolled_at'   => now(),
                ]);

                $student = User::find($studentId);
                if ($student) {
                    $this->notifications->notify(
                        $student,
                        'enrolled',
                        'Enrolled in Course',
                        "You have been enrolled in \"{$course->title}\" for {$yearLevel->name}.",
                        route('student.courses.index')
                    );
                }

                $created++;
            }
        }

        $msg = "{$created} enrollment(s) created.";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped.";
            // Show up to 5 skip reasons to help diagnose issues
            if (!empty($skipReasons)) {
                $uniqueReasons = array_unique($skipReasons);
                $displayReasons = array_slice($uniqueReasons, 0, 5);
                session()->flash('skip_details', $displayReasons);
            }
        }

        $messageType = $created > 0 ? 'success' : 'warning';
        return redirect()->route('admin.enrollments.index')->with($messageType, $msg);
    }

    // ── Destroy ───────────────────────────────────────────────────────────

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();
        return redirect()->route('admin.enrollments.index')->with('success', 'Enrollment removed.');
    }
}

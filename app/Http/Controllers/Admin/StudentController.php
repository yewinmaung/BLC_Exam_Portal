<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Major;
use App\Models\Role;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\ActivityLogService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private EmailService $emailService
    ) {}

    public function index(Request $request)
    {
        $search     = $request->string('search')->trim()->limit(100)->value();
        $yearLevelId = $request->filled('year_level_id') ? (int) $request->year_level_id : null;
        $status      = $request->filled('status') ? $request->status : null;

        $yearLevels = YearLevel::orderBy('level')->get();

        $students = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->with('role')
            ->withCount('enrollments')
            ->when($search, fn ($q) =>
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($yearLevelId, fn ($q) =>
                $q->whereHas('studentYearRecords', fn ($s) =>
                    $s->where('year_level_id', $yearLevelId)
                )
            )
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'yearLevels'));
    }

    public function create()
    {
        YearLevel::ensureDefaults();
        Major::ensureDefaults();

        $academicYears  = AcademicYear::orderByDesc('start_year')->get();
        $currentYearId  = $academicYears->firstWhere('is_current', true)?->id;
        $yearLevels     = YearLevel::orderBy('level')->get();
        $majors         = Major::where('is_active', true)->orderBy('name')->get();
        $courses        = Course::where('is_active', true)->orderBy('title')->get();

        return view('admin.students.create', compact('academicYears', 'currentYearId', 'yearLevels', 'majors', 'courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|min:8',
            'phone'            => 'nullable|string|max:50',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'year_level_id'    => 'nullable|exists:year_levels,id',
            'major_id'         => $this->majorRules($request),
            'semester'         => 'nullable|in:1,2',
            'department'       => 'nullable|string|max:100',
            'course_ids'       => 'nullable|array',
            'course_ids.*'     => 'exists:courses,id',
        ]);

        $studentRole = Role::where('slug', RoleSlug::STUDENT)->firstOrFail();

        $student = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'phone'             => $data['phone'] ?? null,
            'role_id'           => $studentRole->id,
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // Assign to academic year record if provided
        if (!empty($data['academic_year_id']) && !empty($data['year_level_id'])) {
            StudentYearRecord::create([
                'student_id'       => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'year_level_id'    => $data['year_level_id'],
                'semester'         => $data['semester'] ?? '1',
                'department'       => $data['department'] ?? null,
                'major'            => $this->majorNameFromId($data['major_id'] ?? null),
                'status'           => 'active',
            ]);
        }

        // Enroll in courses
        if (!empty($data['course_ids'])) {
            $yearLevel = !empty($data['year_level_id'])
                ? YearLevel::find($data['year_level_id'])?->level ?? 1
                : 1;

            foreach ($data['course_ids'] as $courseId) {
                Enrollment::firstOrCreate([
                    'student_id' => $student->id,
                    'course_id'  => $courseId,
                    'year'       => $yearLevel,
                ], ['enrolled_at' => now()]);
            }
        }

        $this->activityLog->log('student_created', "Created student {$student->email}", $student);

        // Send welcome email
        $this->emailService->sendWelcomeEmail($student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student created successfully.');
    }

    public function show(User $student)
    {
        $this->ensureStudent($student);
        $student->load(['role', 'enrollments.course.teacher']);

        $yearRecords = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $enrolledCourseIds = $student->enrollments()->pluck('course_id')->all();

        return view('admin.students.show', compact(
            'student', 'yearRecords', 'courses', 'academicYears', 'yearLevels', 'enrolledCourseIds'
        ));
    }

    public function edit(User $student)
    {
        $this->ensureStudent($student);

        YearLevel::ensureDefaults();
        Major::ensureDefaults();

        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

        $currentRecord = StudentYearRecord::where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Restrict courses to those valid for the student's academic context.
        // A course is valid if its year_level matches the student's year level (or is 0 = All)
        // AND its academic_year_id matches the student's academic year (or is null = all years)
        // AND its semester matches the student's current semester (or is 0 = Both Semesters).
        $courses = $this->getAllowedCourses($currentRecord);

        $enrolledCourseIds = $student->enrollments()->pluck('course_id')->all();
        $currentMajorId    = Major::resolveIdFromLabel($currentRecord?->major);

        return view('admin.students.edit', compact(
            'student', 'academicYears', 'yearLevels', 'majors', 'courses', 'enrolledCourseIds', 'currentRecord', 'currentMajorId'
        ));
    }

    public function update(Request $request, User $student)
    {
        $this->ensureStudent($student);

        // Resolve the student's current academic record (before validating) so we can
        // build the allowed-course scope used in validation. We use the submitted
        // academic_year_id / year_level_id when provided, falling back to the existing
        // active record, so the scope always matches what was shown in the edit form.
        $currentRecord = StudentYearRecord::where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Build a temporary record-like object for scoping when the admin changed the
        // academic year or year level in the same submit.
        $scopeRecord = (object) [
            'academic_year_id' => $request->input('academic_year_id', $currentRecord?->academic_year_id),
            'year_level_id'    => $request->input('year_level_id',    $currentRecord?->year_level_id),
            'semester'         => $request->input('semester',          $currentRecord?->semester),
            'major_id'         => $request->input('major_id',          Major::resolveIdFromLabel($currentRecord?->major)),
        ];

        $allowedCourseIds = $this->getAllowedCourses($scopeRecord)->pluck('id')->all();

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email,' . $student->id,
            'phone'            => 'nullable|string|max:50',
            'is_active'        => 'boolean',
            'password'         => 'nullable|min:8',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'year_level_id'    => 'nullable|exists:year_levels,id',
            'major_id'         => $this->majorRules($request),
            'semester'         => 'nullable|in:1,2',
            'department'       => 'nullable|string|max:100',
            'course_ids'       => 'nullable|array',
            // Enforce that every submitted course ID is within the allowed scope —
            // this blocks URL/request tampering regardless of UI bypasses.
            'course_ids.*'     => ['integer', 'in:' . implode(',', $allowedCourseIds ?: [0])],
        ]);

        $updateData = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }
        $student->update($updateData);

        // Update/create year record
        if (!empty($data['academic_year_id']) && !empty($data['year_level_id'])) {
            StudentYearRecord::updateOrCreate(
                [
                    'student_id'       => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'year_level_id'    => $data['year_level_id'],
                ],
                [
                    'semester'   => $data['semester'] ?? '1',
                    'department' => $data['department'] ?? null,
                    'major'      => $this->majorNameFromId($data['major_id'] ?? null),
                    'status'     => 'active',
                ]
            );
        }

        // Sync course enrollments
        if (isset($data['course_ids'])) {
            $yearLevel = !empty($data['year_level_id'])
                ? YearLevel::find($data['year_level_id'])?->level ?? 1
                : 1;

            // Remove old enrollments not in new list
            Enrollment::where('student_id', $student->id)
                ->whereNotIn('course_id', $data['course_ids'])
                ->delete();

            // Add new ones
            foreach ($data['course_ids'] as $courseId) {
                Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'course_id' => $courseId, 'year' => $yearLevel],
                    ['enrolled_at' => now()]
                );
            }
        }

        $this->activityLog->log('student_updated', "Updated student {$student->email}", $student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student updated.');
    }

    public function destroy(User $student)
    {
        $this->ensureStudent($student);

        if ($student->id === auth()->id()) {
            return back()->withErrors(['error' => 'Cannot delete your own account.']);
        }

        $email = $student->email;
        $student->forceDelete();

        $this->activityLog->log('student_deleted', "Permanently deleted student {$email}");

        return redirect()->route('admin.students.index')
            ->with('success', "Student account permanently deleted.");
    }

    /**
     * Return courses that are valid for the given student academic record.
     *
     * Matching rules (all must pass):
     *  - is_active = true
     *  - year_level  = 0 (All Year Levels)  OR  = student's year level integer
     *  - semester    = 0 (Both Semesters)    OR  = student's current semester
     *  - academic_year_id is null (unrestricted) OR = student's academic year
     *  - major_id is null (Year 1 / all majors)  OR = student's resolved major
     *
     * When no academic record exists we fall back to all active courses so the
     * admin can still assign a first record.
     *
     * @param  object|null  $record  An object with academic_year_id, year_level_id, semester
     */
    private function getAllowedCourses(?object $record): \Illuminate\Database\Eloquent\Collection
    {
        $query = Course::where('is_active', true);

        if ($record && ($record->year_level_id || $record->academic_year_id)) {
            $yearLevelModel = $record->year_level_id
                ? \App\Models\YearLevel::find($record->year_level_id)
                : null;
            $studentYearLevel       = $yearLevelModel?->level;
            $studentSemester        = (int) ($record->semester ?? 0);
            $studentAcademicYearId  = $record->academic_year_id ?? null;

            // Resolve the student's major from their active StudentYearRecord (major text → Major model)
            $studentMajorId = null;
            if ($record instanceof \App\Models\StudentYearRecord) {
                // It's a real model — check if there's a major_id on it (future-proof)
                $studentMajorId = $record->major_id ?? null;
            }
            if (!$studentMajorId && !empty($record->major)) {
                $studentMajorId = Major::resolveIdFromLabel($record->major);
            }
            // Also allow callers that pass a plain object with major_id
            if (!$studentMajorId && isset($record->major_id)) {
                $studentMajorId = $record->major_id ?? null;
            }

            $query->where(function ($q) use (
                $studentYearLevel, $studentSemester,
                $studentAcademicYearId, $studentMajorId
            ) {
                // Year level: course must be "All" (0) or match student's level
                if ($studentYearLevel !== null) {
                    $q->where(function ($yl) use ($studentYearLevel) {
                        $yl->where('year_level', 0)
                           ->orWhere('year_level', $studentYearLevel);
                    });
                }

                // Semester: course must be "Both" (0) or match student's semester
                if ($studentSemester > 0) {
                    $q->where(function ($sem) use ($studentSemester) {
                        $sem->where('semester', 0)
                            ->orWhere('semester', $studentSemester);
                    });
                }

                // Academic year: null (all years) or exact match
                if ($studentAcademicYearId) {
                    $q->where(function ($ay) use ($studentAcademicYearId) {
                        $ay->whereNull('academic_year_id')
                           ->orWhere('academic_year_id', $studentAcademicYearId);
                    });
                }

                // Major: course must match student's major (majors table FK)
                if ($studentMajorId) {
                    $q->where('major_id', $studentMajorId);
                }
            });
        }

        return $query->with('major')->orderBy('title')->get();
    }

    private function ensureStudent(User $user): void
    {
        if (!$user->isStudent()) abort(404);
    }

    /** @return array<int, mixed> */
    private function majorRules(Request $request): array
    {
        return [
            'nullable',
            'exists:majors,id',
            function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                $yearLevelId = $request->input('year_level_id');
                if (!$yearLevelId) {
                    return;
                }

                $yearLevel = YearLevel::find($yearLevelId);
                if ($yearLevel && $yearLevel->level >= 2 && empty($value)) {
                    $fail('Major is required for Year 2 and above.');
                }
            },
        ];
    }

    private function majorNameFromId(?int $majorId): ?string
    {
        return $majorId ? Major::find($majorId)?->name : null;
    }
}

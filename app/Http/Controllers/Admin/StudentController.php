<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordType;
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
use App\Services\StudentMajorLockService;
use App\Services\YearLevelProgressionValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private EmailService $emailService,
        private YearLevelProgressionValidator $progressionValidator,
        private StudentMajorLockService $majorLockService
    ) {}

    public function index(Request $request)
    {
        $search      = $request->string('search')->trim()->limit(100)->value();
        $yearLevelId = $request->filled('year_level_id') ? (int) $request->year_level_id : null;
        $status      = $request->filled('status') ? $request->status : null;

        $yearLevels    = YearLevel::orderBy('level')->get();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

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

        // ── Build grouped data for the accordion ──────────────────────────
        // Loads ALL active StudentYearRecords (not paginated) for the accordion view.
        // Grouped: Academic Year → Year Level → Semester → Major → students
        $groupedRecords = StudentYearRecord::with([
                'student:id,name,email,is_active',
                'academicYear:id,name,start_year',
                'yearLevel:id,level,name',
            ])
            ->where('status', 'active')
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('year_level_id')
            ->orderBy('semester')
            ->get()
            // Group: AcademicYear → YearLevel → Semester → Major
            ->groupBy([
                fn ($r) => $r->academicYear?->name ?? 'Unassigned',
                fn ($r) => $r->yearLevel?->name    ?? 'Unknown Level',
                fn ($r) => 'Semester ' . ($r->semester ?? '1'),
                fn ($r) => $r->major               ?? 'No Major',
            ]);

        // Sort academic years descending (newest first) — preserve key order after groupBy
        $ayStartYears = AcademicYear::pluck('start_year', 'name')->toArray();

        return view('admin.students.index', compact(
            'students', 'yearLevels', 'academicYears', 'majors', 'groupedRecords', 'ayStartYears'
        ));
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
            'phone'            => 'nullable|string|max:50',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'year_level_id'    => 'nullable|exists:year_levels,id',
            'major_id'         => $this->majorRules($request),
            'semester'         => 'nullable|in:1,2',
            'department'       => 'nullable|string|max:100',
            'record_type'      => 'nullable|in:' . implode(',', RecordType::ALL),
            'remark'           => $this->remarkRules($request),
            'course_ids'       => 'nullable|array',
            'course_ids.*'     => 'exists:courses,id',
        ]);

        $studentRole = Role::where('slug', RoleSlug::STUDENT)->firstOrFail();

        // Generate a 12-char temporary password: 3 upper + 3 lower + 3 digit + 3 symbol
        // Uses random_int() for cryptographic randomness — works on all Laravel/PHP versions
        $temporaryPassword = self::generateTemporaryPassword();

        $student = User::create([
            'name'                          => $data['name'],
            'email'                         => $data['email'],
            'password'                      => Hash::make($temporaryPassword),
            'phone'                         => $data['phone'] ?? null,
            'role_id'                       => $studentRole->id,
            'email_verified_at'             => now(),
            'is_active'                     => true,
            'force_password_change'         => true,
            'temporary_password_expires_at' => now()->addHours(\App\Models\User::TEMP_PASSWORD_EXPIRY_HOURS),
        ]);

        // Assign to academic year record if provided
        if (!empty($data['academic_year_id']) && !empty($data['year_level_id'])) {
            $newYearLevel  = YearLevel::find($data['year_level_id']);
            $recordType    = $data['record_type'] ?? null;

            // Run year-level progression validation before creating the record
            if ($newYearLevel) {
                $progressionError = $this->progressionValidator->validate(
                    $student->id,
                    $newYearLevel->level,
                    (int) $data['academic_year_id'],
                    $recordType
                );
                if ($progressionError) {
                    // Rollback the just-created student and return with error
                    $student->forceDelete();
                    return back()->withInput()->withErrors(['year_level_id' => $progressionError]);
                }

                $majorError = $this->majorLockService->validateMajor(
                    $student->id,
                    $newYearLevel->level,
                    isset($data['major_id']) ? (int) $data['major_id'] : null
                );
                if ($majorError) {
                    $student->forceDelete();
                    return back()->withInput()->withErrors(['major_id' => $majorError]);
                }
            }

            $resolvedMajorId = $newYearLevel
                ? $this->majorLockService->resolveMajorIdForSave(
                    $student->id,
                    $newYearLevel->level,
                    isset($data['major_id']) ? (int) $data['major_id'] : null
                )
                : ($data['major_id'] ?? null);

            StudentYearRecord::create([
                'student_id'       => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'year_level_id'    => $data['year_level_id'],
                'semester'         => $data['semester'] ?? '1',
                'department'       => $data['department'] ?? null,
                'major'            => $this->majorNameFromId($resolvedMajorId),
                'status'           => 'active',
                'record_type'      => $recordType,
                'remark'           => $data['remark'] ?? null,
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

        // Dispatch welcome email job with temporary password (queued, non-blocking)
        \App\Jobs\SendWelcomeAccountJob::dispatch($student->id, $temporaryPassword);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student created successfully. A welcome email with login credentials has been queued.');
    }

    public function show(User $student)
    {
        $this->ensureStudent($student);

        // Load enrollments with course.teacher included so the fallback path can
        // read courses.teacher_id when no exam snapshot exists yet for that year.
        // The primary source (exams.teacher_id) is resolved further below and
        // takes priority — course.teacher is only used as the fallback.
        $student->load(['role', 'enrollments.course.teacher']);

        $yearRecords = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $enrolledCourseIds = $student->enrollments()->pluck('course_id')->all();

        // ── Resolve teacher per enrollment via exams.teacher_id with fallback ──
        //
        // Priority 1 — exams.teacher_id (immutable snapshot):
        //   When an exam exists for the course + the matching academic year,
        //   use that exam's teacher_id. This is who actually taught the course that year.
        //
        // Priority 2 — courses.teacher_id (current assignment / fallback):
        //   When no exam has been created yet for this course in the enrollment year,
        //   fall back to courses.teacher_id. This shows the assigned teacher before
        //   any exam snapshot exists — e.g. a new academic year where exams are pending.
        //
        // CRITICAL: the exam lookup is scoped by BOTH course_id AND academic_year_id.
        // Without the academic year constraint a query by course_id alone would return
        // a 2022-2023 exam (T2) for a student enrolled in 2018-2019 (T1).

        // Build a fast lookup: [year_level][semester] → academic_year_id
        // from the student's own year records (mirrors the blade grouping logic).
        $yrAcadYearMap = [];
        foreach ($yearRecords as $yr) {
            $lvl = $yr->yearLevel?->level ?? 0;
            $sem = (int) ($yr->semester ?? 0);
            $yrAcadYearMap[$lvl][$sem] = $yr->academic_year_id;
            if (! isset($yrAcadYearMap[$lvl][0])) {
                $yrAcadYearMap[$lvl][0] = $yr->academic_year_id;
            }
        }

        // Collect unique (course_id, academic_year_id) pairs needed for the exam query
        $pairs = [];
        foreach ($student->enrollments as $enrollment) {
            $course = $enrollment->course;
            if (! $course) continue;

            $lvl = (int) ($course->year_level ?? 0);
            $sem = (int) ($course->semester   ?? 0);

            $acadYearId = $yrAcadYearMap[$lvl][$sem]
                ?? $yrAcadYearMap[$lvl][0]
                ?? $yrAcadYearMap[0][$sem]
                ?? $yrAcadYearMap[0][0]
                ?? null;

            if ($acadYearId) {
                $pairs[] = ['course_id' => $course->id, 'ay_id' => $acadYearId];
            }
        }

        // Fetch all relevant exam snapshots in one query, build compound-key map:
        //   "courseId:ayId" → teacher User model
        $historicalTeacherMap = [];

        if (! empty($pairs)) {
            $courseIds = array_unique(array_column($pairs, 'course_id'));
            $ayIds     = array_unique(array_column($pairs, 'ay_id'));

            $exams = \App\Models\Exam::with('teacher')
                ->whereIn('course_id', $courseIds)
                ->whereIn('academic_year_id', $ayIds)
                ->whereNotNull('teacher_id')
                ->orderByRaw("FIELD(status, 'closed', 'published', 'approved', 'pending_approval', 'draft')")
                ->orderByDesc('id')
                ->get();

            foreach ($exams as $exam) {
                $key = $exam->course_id . ':' . $exam->academic_year_id;
                if (! isset($historicalTeacherMap[$key])) {
                    $historicalTeacherMap[$key] = $exam->teacher;
                }
            }
        }

        // Attach historicalTeacher: exam snapshot if it exists, else course.teacher fallback.
        foreach ($student->enrollments as $enrollment) {
            $course = $enrollment->course;
            if (! $course) {
                $enrollment->historicalTeacher = null;
                continue;
            }

            $lvl = (int) ($course->year_level ?? 0);
            $sem = (int) ($course->semester   ?? 0);

            $acadYearId = $yrAcadYearMap[$lvl][$sem]
                ?? $yrAcadYearMap[$lvl][0]
                ?? $yrAcadYearMap[0][$sem]
                ?? $yrAcadYearMap[0][0]
                ?? null;

            $key = $course->id . ':' . $acadYearId;

            $enrollment->historicalTeacher = ($acadYearId && isset($historicalTeacherMap[$key]))
                ? $historicalTeacherMap[$key]       // Priority 1: exam snapshot (immutable)
                : $course->teacher;                 // Priority 2: current course assignment
        }

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

        $currentYearLevel = $currentRecord?->yearLevel?->level
            ?? ($currentRecord?->year_level_id
                ? YearLevel::find($currentRecord->year_level_id)?->level
                : null);

        $majorLocked = $currentYearLevel !== null
            && $this->majorLockService->isMajorLocked($student->id, $currentYearLevel);

        $lockedMajorId = $majorLocked
            ? $this->majorLockService->getCanonicalMajorId($student->id)
            : null;

        $lockedMajorCode = $lockedMajorId ? Major::find($lockedMajorId)?->code : null;

        if ($majorLocked && $lockedMajorId) {
            $currentMajorId = $lockedMajorId;
        }

        return view('admin.students.edit', compact(
            'student', 'academicYears', 'yearLevels', 'majors', 'courses', 'enrolledCourseIds',
            'currentRecord', 'currentMajorId', 'majorLocked', 'lockedMajorId', 'lockedMajorCode'
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

        $targetYearLevelId = $scopeRecord->year_level_id;
        $targetLevel = $targetYearLevelId ? YearLevel::find($targetYearLevelId)?->level : null;
        if ($targetLevel && $this->majorLockService->isMajorLocked($student->id, $targetLevel)) {
            $scopeRecord->major_id = $this->majorLockService->getCanonicalMajorId($student->id);
        }

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
            'record_type'      => 'nullable|in:' . implode(',', RecordType::ALL),
            'remark'           => $this->remarkRules($request),
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
            $newYearLevel = YearLevel::find($data['year_level_id']);
            $recordType   = $data['record_type'] ?? null;

            $isEditingSameRecord = $currentRecord
                && (int) $currentRecord->academic_year_id === (int) $data['academic_year_id']
                && (int) $currentRecord->year_level_id    === (int) $data['year_level_id'];

            if ($newYearLevel) {
                // Determine whether this is editing the EXISTING record or adding a NEW one.
                //
                // EDIT path: submitted values exactly match the current active record
                //   → exclude that record from the timeline so it isn't validated against itself.
                //
                // CREATE path: the admin changed academic year or year level
                //   → treat it as appending a new record; include ALL existing records.
                //
                if ($isEditingSameRecord) {
                    // EDIT path: exclude the record being updated to avoid self-conflict.
                    $progressionError = $this->progressionValidator->validateEdit(
                        $student->id,
                        $newYearLevel->level,
                        (int) $data['academic_year_id'],
                        $recordType,
                        $currentRecord->id
                    );
                } else {
                    // CREATE path: adding a new (academic_year, year_level) combination.
                    $progressionError = $this->progressionValidator->validate(
                        $student->id,
                        $newYearLevel->level,
                        (int) $data['academic_year_id'],
                        $recordType
                    );
                }

                if ($progressionError) {
                    return back()->withInput()->withErrors(['year_level_id' => $progressionError]);
                }

                $majorError = $this->majorLockService->validateMajor(
                    $student->id,
                    $newYearLevel->level,
                    isset($data['major_id']) ? (int) $data['major_id'] : null
                );
                if ($majorError) {
                    return back()->withInput()->withErrors(['major_id' => $majorError]);
                }
            }

            $resolvedMajorId = $newYearLevel
                ? $this->majorLockService->resolveMajorIdForSave(
                    $student->id,
                    $newYearLevel->level,
                    isset($data['major_id']) ? (int) $data['major_id'] : null
                )
                : ($data['major_id'] ?? null);

            StudentYearRecord::updateOrCreate(
                [
                    'student_id'       => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'year_level_id'    => $data['year_level_id'],
                ],
                [
                    'semester'    => $data['semester'] ?? '1',
                    'department'  => $data['department'] ?? null,
                    'major'       => $this->majorNameFromId($resolvedMajorId),
                    'status'      => 'active',
                    'record_type' => $recordType,
                    'remark'      => $data['remark'] ?? null,
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

    /**
     * Generate a cryptographically random 12-character temporary password.
     * Format: 3 uppercase + 3 lowercase + 3 digits + 3 symbols, then shuffled.
     * Uses random_int() — available in PHP 7+ and all Laravel versions.
     */
    private static function generateTemporaryPassword(): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';
        $symbols = '!@#$%&*';

        $pick = function (string $charset, int $count): string {
            $result = '';
            $len    = strlen($charset);
            for ($i = 0; $i < $count; $i++) {
                $result .= $charset[random_int(0, $len - 1)];
            }
            return $result;
        };

        $raw = $pick($upper, 3) . $pick($lower, 3) . $pick($digits, 3) . $pick($symbols, 3);

        // Shuffle using Fisher-Yates with random_int for uniform distribution
        $chars = str_split($raw);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j             = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
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

    /** @return array<int, mixed> */
    private function remarkRules(Request $request): array
    {
        $type = $request->input('record_type') ?? RecordType::NORMAL;
        $requiresRemark = in_array($type, [RecordType::TRANSFER, RecordType::READMISSION], true);

        return $requiresRemark
            ? ['required', 'string', 'max:1000']
            : ['nullable', 'string', 'max:1000'];
    }
}

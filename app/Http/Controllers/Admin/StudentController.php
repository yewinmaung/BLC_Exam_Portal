<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
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

    public function index()
    {
        $students = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->with('role')
            ->withCount('enrollments')
            ->latest()
            ->paginate(15);

        return view('admin.students.index', compact('students'));
    }

    public function create()
    {
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $courses       = Course::where('is_active', true)->orderBy('title')->get();

        return view('admin.students.create', compact('academicYears', 'yearLevels', 'courses'));
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
            'semester'         => 'nullable|in:1,2',
            'department'       => 'nullable|string|max:100',
            'major'            => 'nullable|string|max:100',
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
                'major'            => $data['major'] ?? null,
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

        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $courses       = Course::where('is_active', true)->orderBy('title')->get();
        $enrolledCourseIds = $student->enrollments()->pluck('course_id')->all();

        $currentRecord = StudentYearRecord::where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return view('admin.students.edit', compact(
            'student', 'academicYears', 'yearLevels', 'courses', 'enrolledCourseIds', 'currentRecord'
        ));
    }

    public function update(Request $request, User $student)
    {
        $this->ensureStudent($student);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email,' . $student->id,
            'phone'            => 'nullable|string|max:50',
            'is_active'        => 'boolean',
            'password'         => 'nullable|min:8',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'year_level_id'    => 'nullable|exists:year_levels,id',
            'semester'         => 'nullable|in:1,2',
            'department'       => 'nullable|string|max:100',
            'major'            => 'nullable|string|max:100',
            'course_ids'       => 'nullable|array',
            'course_ids.*'     => 'exists:courses,id',
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
                    'major'      => $data['major'] ?? null,
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

    private function ensureStudent(User $user): void
    {
        if (!$user->isStudent()) abort(404);
    }
}

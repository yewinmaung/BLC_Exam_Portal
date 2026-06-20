<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\Course;
use App\Services\ActivityLogService;
use App\Services\CourseAssignmentService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private CourseAssignmentService $courseAssignment,
        private EmailService $emailService
    ) {
    }

    public function index()
    {
        $teachers = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))
            ->with('role')
            ->withCount(['taughtCourses', 'examsAsTeacher'])
            ->latest()
            ->paginate(15);

        return view('admin.teachers.index', compact('teachers'));
    }

    public function show(User $teacher)
    {
        $this->ensureTeacher($teacher);

        $teacher->load([
            'role',
            'taughtCourses' => fn ($q) => $q->latest()->limit(10),
            'examsAsTeacher' => fn ($q) => $q->with('course')->latest()->limit(10),
        ]);

        $stats = [
            'courses' => $teacher->taughtCourses()->count(),
            'exams'   => $teacher->examsAsTeacher()->count(),
            'pending' => $teacher->examsAsTeacher()->where('status', 'pending_approval')->count(),
        ];

        [$courses, $assignedCourseIds] = $this->teacherCourseOptions($teacher);

        return view('admin.teachers.show', compact('teacher', 'stats', 'courses', 'assignedCourseIds'));
    }

    public function edit(User $teacher)
    {
        $this->ensureTeacher($teacher);

        [$courses, $assignedCourseIds] = $this->teacherCourseOptions($teacher);

        return view('admin.teachers.edit', compact('teacher', 'courses', 'assignedCourseIds'));
    }

    private function teacherCourseOptions(User $teacher): array
    {
        $courses = Course::where('is_active', true)->orderBy('title')->get();
        $assignedCourseIds = $teacher->taughtCourses()->pluck('id')->all();

        return [$courses, $assignedCourseIds];
    }

    public function update(Request $request, User $teacher)
    {
        $this->ensureTeacher($teacher);

        $data = $request->validate([
            'course_ids'   => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        $this->courseAssignment->syncTeacherCourses($teacher, $data['course_ids'] ?? []);

        $this->activityLog->log('teacher_courses_updated', "Updated courses for teacher {$teacher->email}", $teacher);

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', 'Teacher courses updated.');
    }

    public function create()
    {
        $courses = Course::where('is_active', true)->orderBy('title')->get();
        $assignedCourseIds = [];

        return view('admin.teachers.create', compact('courses', 'assignedCourseIds'));
    }

    public function store(Request $request)
    {
        $teacherRole = Role::where('slug', RoleSlug::TEACHER)->firstOrFail();

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8',
            'phone'        => 'nullable|string|max:50',
            'course_ids'   => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        $teacher = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'phone'             => $data['phone'] ?? null,
            'role_id'           => $teacherRole->id,
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        if (!empty($data['course_ids'])) {
            $this->courseAssignment->syncTeacherCourses($teacher, $data['course_ids']);
        }

        $this->activityLog->log('teacher_created', "Created teacher {$teacher->email}", $teacher);

        // Send welcome email
        $this->emailService->sendWelcomeEmail($teacher);

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', 'Teacher created. You can edit assigned courses below.');
    }

    private function ensureTeacher(User $user): void
    {
        if (!$user->isTeacher()) {
            abort(404);
        }
    }
}

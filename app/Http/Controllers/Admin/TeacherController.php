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

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->limit(100)->value();
        $status = $request->filled('status') ? $request->status : null;

        $teachers = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))
            ->with('role')
            ->withCount(['taughtCourses', 'examsAsTeacher'])
            ->when($search, fn ($q) =>
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

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
        $data=$request->validate([
            
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . $teacher->id,
           
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
        $teacher->update($updateData);
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
            'phone'        => 'nullable|string|max:50',
            'course_ids'   => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        // Generate a 12-char temporary password: 3 upper + 3 lower + 3 digit + 3 symbol
        // Uses random_int() for cryptographic randomness — works on all Laravel/PHP versions
        $temporaryPassword = self::generateTemporaryPassword();

        $teacher = User::create([
            'name'                          => $data['name'],
            'email'                         => $data['email'],
            'password'                      => Hash::make($temporaryPassword),
            'phone'                         => $data['phone'] ?? null,
            'role_id'                       => $teacherRole->id,
            'email_verified_at'             => now(),
            'is_active'                     => true,
            'force_password_change'         => true,
            'temporary_password_expires_at' => now()->addHours(\App\Models\User::TEMP_PASSWORD_EXPIRY_HOURS),
        ]);

        if (!empty($data['course_ids'])) {
            $this->courseAssignment->syncTeacherCourses($teacher, $data['course_ids']);
        }

        $this->activityLog->log('teacher_created', "Created teacher {$teacher->email}", $teacher);

        // Dispatch welcome email job with temporary password (queued, non-blocking)
        \App\Jobs\SendWelcomeAccountJob::dispatch($teacher->id, $temporaryPassword);

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', 'Teacher created. A welcome email with login credentials has been queued.');
    }

    private function ensureTeacher(User $user): void
    {
        if (!$user->isTeacher()) {
            abort(404);
        }
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
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function show()
    {
        $teacher = auth()->user();

        if (!$teacher->isTeacher()) {
            abort(403, 'Only teachers can access this page.');
        }

        $teacher->load([
            'taughtCourses' => fn ($q) => $q->with(['academicYear'])->latest(),
            'examsAsTeacher' => fn ($q) => $q->with('course')->latest()->limit(10),
        ]);

        $stats = [
            'courses' => $teacher->taughtCourses()->count(),
            'exams'   => $teacher->examsAsTeacher()->count(),
            'pending' => $teacher->examsAsTeacher()->where('status', 'pending_approval')->count(),
        ];

        return view('teacher.profile.show', compact('teacher', 'stats'));
    }

    public function courseDetail(\App\Models\Course $course)
    {
        $teacher = auth()->user();

        if (!$teacher->isTeacher()) {
            abort(403);
        }

        // Ensure the course belongs to this teacher
        if ($course->teacher_id !== $teacher->id) {
            abort(403, 'You are not assigned to this course.');
        }

        $course->load(['academicYear', 'exams' => fn ($q) => $q->where('teacher_id', $teacher->id)->latest()]);

        return view('teacher.profile.course-detail', compact('course'));
    }

    public function edit()
    {
        $teacher = auth()->user();

        if (!$teacher->isTeacher()) {
            abort(403);
        }

        return view('teacher.profile.edit', compact('teacher'));
    }

    public function update(Request $request)
    {
        $teacher = auth()->user();

        if (!$teacher->isTeacher()) {
            abort(403);
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($teacher->id)],
            'phone'    => 'nullable|string|max:50',
            'password' => 'nullable|min:8',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $teacher->update($data);

        $this->activityLog->log('teacher_profile_updated', "Updated profile {$teacher->email}", $teacher);

        return redirect()->route('teacher.profile.show')->with('success', 'Profile updated successfully.');
    }
}

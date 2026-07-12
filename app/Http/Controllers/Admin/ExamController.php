<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\EmailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private NotificationService $notifications,
        private EmailService $emailService
    ) {
    }

    public function index(Request $request)
    {
        $search   = $request->string('search')->trim()->limit(100)->value();
        $status   = $request->filled('status') ? $request->status : null;
        $courseId = $request->filled('course_id') ? (int) $request->course_id : null;

        $courses = Course::where('is_active', true)->orderBy('title')->get(['id', 'title']);

        $exams = Exam::with(['course', 'teacher', 'activeSchedule'])
            ->when($search, fn ($q) =>
                $q->where('title', 'like', "%{$search}%")
            )
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Auto-mark all unread 'exam' category notifications as read
        // when the user visits this page (badge clears on page load).
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'exam');

        return view('admin.exams.index', compact('exams', 'courses'));
    }

    public function show(Exam $exam)
    {
        $exam->load(['course', 'teacher', 'questions.answers', 'schedules', 'latestSchedule']);

        return view('admin.exams.show', compact('exam'));
    }

    public function results(Exam $exam)
    {
        $exam->load(['course', 'teacher', 'latestSchedule']);

        // Get all results for this exam with student details and attempts
        $results = \App\Models\Result::with(['student', 'attempt'])
            ->where('exam_id', $exam->id)
            ->orderByDesc('percentage')
            ->get();

        // Get all enrolled students who haven't taken the exam yet
        $enrolledStudentIds = $exam->course->enrollments()->pluck('student_id');
        $resultStudentIds = $results->pluck('student_id');
        
        $absentStudents = \App\Models\User::whereIn('id', $enrolledStudentIds)
            ->whereNotIn('id', $resultStudentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Calculate statistics
        $stats = [
            'total_enrolled' => $enrolledStudentIds->count(),
            'total_taken'    => $results->count(),
            'total_absent'   => $absentStudents->count(),
            'passed'         => $results->where('is_passed', true)->count(),
            'failed'         => $results->where('is_passed', false)->count(),
            'avg_score'      => $results->count() > 0 ? round($results->avg('percentage'), 2) : 0,
            'highest_score'  => $results->max('percentage') ?? 0,
            'lowest_score'   => $results->min('percentage') ?? 0,
        ];

        return view('admin.exams.results', compact('exam', 'results', 'absentStudents', 'stats'));
    }

    public function approve(Exam $exam)
    {
        if ($exam->status !== 'pending_approval') {
            return back()->withErrors(['error' => 'Exam is not pending approval.']);
        }

        $exam->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        // Notify the teacher
        $this->notifications->notify(
            $exam->teacher,
            'exam_approved',
            'Exam Approved ✓',
            "Your exam \"{$exam->title}\" has been approved by admin. It will be scheduled and published soon.",
            route('teacher.exams.show', $exam)
        );

        $this->activityLog->log('exam_approved', "Approved exam: {$exam->title}", $exam);

        return back()->with('success', 'Exam approved. Teacher has been notified.');
    }

    public function schedule(Request $request, Exam $exam)
    {
        // A schedule can only be set once. If one already exists, reject the request.
        if ($exam->schedules()->exists()) {
            return back()->withErrors(['error' => 'A schedule has already been set for this exam and cannot be changed.']);
        }

        $data = $request->validate([
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after:starts_at',
            'duration_minutes' => 'required|integer|min:1',
            'attempt_limit'    => 'required|integer|min:1',
            'target_year'      => 'nullable|integer|min:1|max:5',
        ]);

        ExamSchedule::create([
            'exam_id' => $exam->id,
            ...$data,
        ]);

        return back()->with('success', 'Exam schedule set.');
    }

    public function updateSchedule(Request $request, Exam $exam, ExamSchedule $schedule)
    {
        // Schedule is set once and cannot be modified.
        return back()->withErrors(['error' => 'The exam schedule cannot be changed after it has been set.']);
    }

    public function deleteSchedule(Exam $exam, ExamSchedule $schedule)
    {
        // Schedule is set once and cannot be deleted.
        return back()->withErrors(['error' => 'The exam schedule cannot be deleted after it has been set.']);
    }

    public function publish(Exam $exam)
    {
        $schedule = $exam->latestSchedule;
        if (!$schedule) {
            return back()->withErrors(['error' => 'Create a schedule before publishing.']);
        }

        $exam->update(['status' => 'published']);
        $schedule->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        $enrolledStudents = $exam->course->students;
        foreach ($enrolledStudents as $student) {
            $this->notifications->notify(
                $student,
                'exam_published',
                'New Exam Available 📝',
                "Exam \"{$exam->title}\" for {$exam->course->title} is now live. Good luck!",
                route('student.exams.show', $exam)
            );
            if ($student->email) {
                try {
                    $this->emailService->sendTemplate(
                        'exam_published',
                        $student->email,
                        $student->name,
                        [
                            'student_name' => $student->name,
                            'exam_name'    => $exam->title,
                            'course_name'  => $exam->course->title ?? '',
                        ],
                        'exam_published',
                        $student->id,
                        true  // queue
                    );
                } catch (\Throwable $e) {
                    logger()->error('ExamPublishedMail failed: ' . $e->getMessage());
                }
            }
        }

        // Also notify the teacher
        $this->notifications->notify(
            $exam->teacher,
            'exam_published',
            'Your Exam is Now Live 🎉',
            "Your exam \"{$exam->title}\" has been published and is now available to students.",
            route('teacher.exams.show', $exam)
        );

        $this->activityLog->log('exam_published', "Published exam: {$exam->title}", $exam);

        return back()->with('success', 'Exam published successfully.');
    }

    public function close(Exam $exam)
    {
        if ($exam->status !== 'published') {
            return back()->withErrors(['error' => 'Only published exams can be closed.']);
        }

        $exam->update(['status' => 'closed']);

        $exam->activeSchedule?->update(['is_published' => false]);

        $this->activityLog->log('exam_closed', "Closed exam: {$exam->title}", $exam);

        return back()->with('success', 'Exam closed. Students can no longer start or continue this exam.');
    }

    public function open(Exam $exam)
    {
        if ($exam->status !== 'closed') {
            return back()->withErrors(['error' => 'Only closed exams can be reopened.']);
        }

        $schedule = $exam->latestSchedule;
        if (!$schedule) {
            return back()->withErrors(['error' => 'No schedule found. Create a schedule before reopening.']);
        }

        $exam->update(['status' => 'published']);
        $schedule->update([
            'is_published' => true,
            'published_at' => $schedule->published_at ?? now(),
            'published_by' => $schedule->published_by ?? auth()->id(),
        ]);

        $this->activityLog->log('exam_opened', "Reopened exam: {$exam->title}", $exam);

        return back()->with('success', 'Exam reopened. Students can access it again during the active schedule window.');
    }
}

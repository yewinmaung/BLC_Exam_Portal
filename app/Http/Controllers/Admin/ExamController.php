<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSchedule;
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

    public function index()
    {
        $exams = Exam::with(['course', 'teacher', 'activeSchedule'])->latest()->get();

        return view('admin.exams.index', compact('exams'));
    }

    public function show(Exam $exam)
    {
        $exam->load(['course', 'teacher', 'questions.answers', 'schedules', 'latestSchedule']);

        return view('admin.exams.show', compact('exam'));
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

        return back()->with('success', 'Exam schedule created.');
    }

    public function updateSchedule(Request $request, Exam $exam, ExamSchedule $schedule)
    {
        if ($schedule->exam_id !== $exam->id) {
            abort(404);
        }

        $data = $request->validate([
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after:starts_at',
            'duration_minutes' => 'required|integer|min:1',
            'attempt_limit'    => 'required|integer|min:1',
            'target_year'      => 'nullable|integer|min:1|max:5',
        ]);

        $schedule->update($data);

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function deleteSchedule(Exam $exam, ExamSchedule $schedule)
    {
        if ($schedule->exam_id !== $exam->id) {
            abort(404);
        }

        if ($schedule->is_published) {
            return back()->withErrors(['error' => 'Cannot delete a published schedule. Close the exam first.']);
        }

        $schedule->delete();

        return back()->with('success', 'Schedule deleted.');
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

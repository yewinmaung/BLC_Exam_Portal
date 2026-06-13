<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ReAttemptRequest;
use App\Services\ExamAccessService;
use App\Services\ReAttemptService;
use Illuminate\Http\Request;

class ReAttemptController extends Controller
{
    public function __construct(
        private ReAttemptService $service,
        private ExamAccessService $examAccess
    ) {}

    public function index()
    {
        $requests = ReAttemptRequest::with(['exam.course', 'teacher', 'approver', 'logs'])
            ->where('student_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('student.reattempts.index', compact('requests'));
    }

    public function create(Exam $exam)
    {
        // Only allow if student is enrolled
        if (!$exam->course->enrollments()->where('student_id', auth()->id())->exists()) {
            abort(403);
        }

        // Only allow for missed exam: schedule ended and student has 0 completed attempts
        $schedule = $exam->student_schedule;
        if (!$schedule || !$this->examAccess->isScheduleEnded($schedule)) {
            return back()->withErrors(['error' => 'This exam is not missed (schedule not ended).']);
        }

        $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->whereIn('status', ['submitted', 'terminated', 'suspicious'])
            ->count();

        if ($usedAttempts > 0) {
            return back()->withErrors(['error' => 'You already attempted this exam. Ask teacher for re-attempt if failed/disqualified.']);
        }

        return view('student.reattempts.create', compact('exam'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'reason'  => 'required|string|max:1000',
        ]);

        $exam = Exam::with(['course.enrollments', 'teacher', 'latestSchedule', 'activeSchedule'])->findOrFail($data['exam_id']);

        if (!$exam->course->enrollments()->where('student_id', auth()->id())->exists()) {
            abort(403);
        }

        // One pending request per student per exam
        $exists = ReAttemptRequest::where('student_id', auth()->id())
            ->where('exam_id', $exam->id)
            ->where('status', 'pending')
            ->exists();
        if ($exists) {
            return back()->withErrors(['error' => 'A pending request already exists for this exam.']);
        }

        // Missed only
        $schedule = $exam->student_schedule;
        if (!$schedule || !$this->examAccess->isScheduleEnded($schedule)) {
            return back()->withErrors(['error' => 'This exam is not missed.']);
        }

        $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->whereIn('status', ['submitted', 'terminated', 'suspicious'])
            ->count();
        if ($usedAttempts > 0) {
            return back()->withErrors(['error' => 'You already attempted this exam.']);
        }

        $this->service->createStudentRequest(auth()->user(), $exam, $data['reason']);

        return redirect()->route('student.reattempts.index')->with('success', 'Request sent to your course teacher.');
    }
}

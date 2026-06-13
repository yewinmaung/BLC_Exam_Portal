<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ReAttemptLog;
use App\Models\ReAttemptRequest;
use App\Services\ExamAccessService;
use App\Services\GradingService;
use Illuminate\Support\Str;

class ExamController extends Controller
{
    public function __construct(
        private ExamAccessService $examAccess,
        private GradingService $grading
    ) {
    }

    public function index()
    {
        $studentId = auth()->id();
        $exams = Exam::with(['course', 'activeSchedule'])
            ->whereIn('status', ['approved', 'published'])
            ->whereHas('course.enrollments', fn ($q) => $q->where('student_id', $studentId))
            ->latest()
            ->paginate(15);

        return view('student.exams.index', compact('exams'));
    }

    public function show(Exam $exam)
    {
        $exam->load(['questions.answers', 'course', 'latestSchedule']);
        $schedule        = $exam->student_schedule;
        $scheduleEnded   = $this->examAccess->scheduleHasEnded($exam);
        $canTake         = $this->examAccess->studentCanTakeExam(auth()->user(), $exam);
        $canViewAnswers  = $this->examAccess->canViewCorrectAnswers(auth()->user(), $exam);

        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->with('result')
            ->get();

        // Only show result after schedule ends
        $result = $scheduleEnded ? $attempts->first()?->result : null;

        return view('student.exams.show', compact(
            'exam', 'schedule', 'canTake', 'canViewAnswers',
            'attempts', 'result', 'scheduleEnded'
        ));
    }

    public function start(Exam $exam)
    {
        if (!$this->examAccess->studentCanTakeExam(auth()->user(), $exam)) {
            $schedule     = $exam->student_schedule;
            $attemptLimit = max(1, (int) ($schedule?->attempt_limit ?? 1));
            $approvedCount = ReAttemptRequest::where('student_id', auth()->id())
                ->where('exam_id', $exam->id)
                ->where('status', 'approved')
                ->count();
            $effectiveLimit = min(3, $attemptLimit + $approvedCount);
            $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
                ->where('student_id', auth()->id())
                ->whereIn('status', ['submitted', 'terminated', 'suspicious'])
                ->count();

            if ($usedAttempts >= $effectiveLimit) {
                $pendingRequest = ReAttemptRequest::where('student_id', auth()->id())
                    ->where('exam_id', $exam->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($pendingRequest) {
                    return back()->withErrors(['error' => 'Your re-attempt request is pending admin approval. Please wait.']);
                }

                $rejectedRequest = ReAttemptRequest::where('student_id', auth()->id())
                    ->where('exam_id', $exam->id)
                    ->where('status', 'rejected')
                    ->latest()
                    ->first();

                if ($rejectedRequest) {
                    return back()->withErrors(['error' => 'Your re-attempt request was rejected. Reason: ' . ($rejectedRequest->admin_remark ?? 'No reason provided.')]);
                }

                return back()->withErrors(['error' => 'Maximum attempts reached (3).']);
            }

            $approvedWithWindow = ReAttemptRequest::where('student_id', auth()->id())
                ->where('exam_id', $exam->id)
                ->where('status', 'approved')
                ->whereNotNull('re_attempt_start_at')
                ->whereNotNull('re_attempt_end_at')
                ->latest()
                ->first();

            if ($approvedWithWindow) {
                ReAttemptLog::create([
                    'request_id' => $approvedWithWindow->id,
                    'action' => 'exam_access',
                    'actor_id' => auth()->id(),
                    'actor_role' => 'student',
                    'remarks' => 'Student attempted access outside re-attempt window.',
                ]);

                return back()->withErrors([
                    'error' => 'Re-attempt is approved, but access is allowed only between '
                        . $approvedWithWindow->re_attempt_start_at->format('M d, Y H:i')
                        . ' and '
                        . $approvedWithWindow->re_attempt_end_at->format('M d, Y H:i') . '.'
                ]);
            }

            return redirect()->route('student.exams.index')
                ->withErrors(['error' => 'Exam is not available.']);
        }

        $schedule     = $exam->student_schedule;
        $attemptCount = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->whereIn('status', ['submitted', 'terminated', 'suspicious'])
            ->count();

        // Resume active attempt if exists
        $active = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->where('status', 'in_progress')
            ->first();

        if ($active) {
            return redirect()->route('student.exam.take', $active);
        }

        $token = \Illuminate\Support\Str::random(60);
        auth()->user()->update(['exam_session_token' => $token]);
        session(['exam_session_token' => $token]);

        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'schedule_id'    => $schedule->id,
            'student_id'     => auth()->id(),
            'attempt_number' => $attemptCount + 1,
            'status'         => 'in_progress',
            'started_at'     => now(),
            'expires_at'     => now()->addMinutes($schedule->duration_minutes),
            'session_token'  => $token,
        ]);

        return redirect()->route('student.exam.take', $attempt);
    }
}

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
        
        // Get regular published exams where student is enrolled
        $exams = Exam::with(['course', 'activeSchedule'])
            ->where('status', 'published')
            ->whereHas('course.enrollments', fn ($q) => $q->where('student_id', $studentId))
            ->latest()
            ->paginate(15);

        // Additionally, get exams with approved re-attempts for this student (even if not published)
        $reattemptExamIds = ReAttemptRequest::where('student_id', $studentId)
            ->where('status', 'approved')
            ->pluck('exam_id')
            ->toArray();

        if (!empty($reattemptExamIds)) {
            $reattemptExams = Exam::with(['course', 'activeSchedule'])
                ->whereIn('id', $reattemptExamIds)
                ->whereHas('course.enrollments', fn ($q) => $q->where('student_id', $studentId))
                ->latest()
                ->get();

            // Merge re-attempt exams with regular exams (avoid duplicates)
            $existingIds = $exams->pluck('id')->toArray();
            $additionalExams = $reattemptExams->filter(fn($exam) => !in_array($exam->id, $existingIds));
            
            // Since $exams is paginated, we need to work with the collection
            // For simplicity, we'll add re-attempt exams to the current page items
            $exams->setCollection($exams->getCollection()->merge($additionalExams));
        }

        $securityTerminatedAttempts = ExamAttempt::where('student_id', $studentId)
            ->where('status', 'terminated_pending_review')
            ->with(['exam', 'cheatingLogs'])
            ->latest('terminated_at')
            ->get();

        // Mark exam notifications as read when student opens Exams page
        \App\Models\UserNotification::markCategoryRead($studentId, 'exam');

        return view('student.exams.index', compact('exams', 'securityTerminatedAttempts'));
    }

    public function show(Exam $exam)
    {
        // Check if student has an approved re-attempt for this exam
        $hasApprovedReattempt = ReAttemptRequest::where('student_id', auth()->id())
            ->where('exam_id', $exam->id)
            ->where('status', 'approved')
            ->exists();

        // Students may only view published exams, OR exams they have approved re-attempts for
        if ($exam->status !== 'published' && !$hasApprovedReattempt) {
            abort(404);
        }

        $exam->load(['questions.answers', 'course', 'latestSchedule']);
        $schedule        = $exam->student_schedule;
        $scheduleEnded   = $this->examAccess->scheduleHasEnded($exam);
        $canTake         = $this->examAccess->studentCanTakeExam(auth()->user(), $exam);
        $canViewAnswers  = $this->examAccess->canViewCorrectAnswers(auth()->user(), $exam);

        // Load all attempts with their own answers and result.
        // Each attempt keeps its own studentAnswers — never merged across attempts.
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->with([
                'result',
                // Only load answers for finalized attempts (security: no in-progress peaking)
                'studentAnswers' => fn ($q) => $q->with(['answer', 'question']),
            ])
            ->orderBy('attempt_number')
            ->get();

        // Finalized = submitted or security-terminated (never in_progress)
        $finalizedAttempts = $attempts->filter(fn ($a) => in_array($a->status, [
            'submitted', 'terminated', 'suspicious', 'terminated_pending_review', 'rejected',
        ]))->values();

        // Legacy single-result variable (used in the top result card when only 1 attempt)
        $result = ($scheduleEnded && $attempts->first()?->result?->is_published)
            ? $attempts->first()->result
            : null;

        return view('student.exams.show', compact(
            'exam', 'schedule', 'canTake', 'canViewAnswers',
            'attempts', 'finalizedAttempts', 'result', 'scheduleEnded'
        ));
    }

    public function start(Exam $exam)
    {
        // Server-side protection: Verify student has access to this exam
        $hasApprovedReattempt = ReAttemptRequest::where('student_id', auth()->id())
            ->where('exam_id', $exam->id)
            ->where('status', 'approved')
            ->exists();

        // Only allow if exam is published OR student has approved re-attempt
        if ($exam->status !== 'published' && !$hasApprovedReattempt) {
            abort(404);
        }

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
                ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
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
            ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
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

        // Generate a per-student question order exactly ONCE at attempt creation.
        // Only shuffle when the teacher has enabled randomization for this exam.
        // Shuffle is server-side only; the client never sees or controls the order.
        $questionIds = $exam->questions()->orderBy('order')->pluck('id')->toArray();
        if ($exam->shuffle_questions) {
            shuffle($questionIds);
        }

        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'schedule_id'    => $schedule->id,
            'student_id'     => auth()->id(),
            'attempt_number' => $attemptCount + 1,
            'status'         => 'in_progress',
            'started_at'     => now(),
            'expires_at'     => now()->addMinutes($schedule->duration_minutes),
            'session_token'  => $token,
            'question_order' => $questionIds,
        ]);

        return redirect()->route('student.exam.take', $attempt);
    }
}

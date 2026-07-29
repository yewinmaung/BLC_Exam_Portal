<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
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
            ->where('status', 'published')
            ->whereHas('course.enrollments', fn ($q) => $q->where('student_id', $studentId))
            ->latest()
            ->paginate(15);

        $examIds = $exams->pluck('id');

        // Active in_progress attempts — drives Continue/Reconnect/recovery buttons.
        // Select disconnected_at so the card can show the recovery countdown.
        $activeAttempts = ExamAttempt::where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->whereIn('exam_id', $examIds)
            ->get(['id', 'exam_id', 'student_id', 'status', 'started_at', 'expires_at', 'disconnected_at'])
            ->keyBy('exam_id');

        // Finalized attempts — submitted, terminated, suspicious, rejected.
        // Used to show "View Result" or "View" instead of "Start" when the
        // student has already taken the exam (or been terminated from it).
        // Keyed by exam_id; picks the most recent attempt per exam.
        $finalizedAttempts = ExamAttempt::where('student_id', $studentId)
            ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected', 'terminated_pending_review'])
            ->whereIn('exam_id', $examIds)
            ->orderByDesc('id')
            ->get(['id', 'exam_id', 'student_id', 'status', 'attempt_number'])
            ->keyBy('exam_id');

        $securityTerminatedAttempts = ExamAttempt::where('student_id', $studentId)
            ->where('status', 'terminated_pending_review')
            ->with(['exam', 'cheatingLogs'])
            ->latest('terminated_at')
            ->get();

        // Mark exam notifications as read when student opens Exams page
        \App\Models\UserNotification::markCategoryRead($studentId, 'exam');

        return view('student.exams.index', compact(
            'exams', 'securityTerminatedAttempts', 'activeAttempts', 'finalizedAttempts'
        ));
    }

    public function show(Exam $exam)
    {
        // Students may only view published exams
        if ($exam->status !== 'published') {
            abort(404);
        }

        $exam->load(['questions.answers', 'course', 'latestSchedule']);
        $schedule       = $exam->student_schedule;
        $scheduleEnded  = $this->examAccess->scheduleHasEnded($exam);
        $canTake        = $this->examAccess->studentCanTakeExam(auth()->user(), $exam);
        $canViewAnswers = $this->examAccess->canViewCorrectAnswers(auth()->user(), $exam);

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
        // Only allow access to published exams
        if ($exam->status !== 'published') {
            abort(404);
        }

        if (!$this->examAccess->studentCanTakeExam(auth()->user(), $exam)) {
            $usedAttempts = ExamAttempt::where('exam_id', $exam->id)
                ->where('student_id', auth()->id())
                ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
                ->count();

            $schedule     = $exam->student_schedule;
            $attemptLimit = max(1, (int) ($schedule?->attempt_limit ?? 1));

            if ($usedAttempts >= $attemptLimit) {
                return back()->withErrors(['error' => 'Maximum attempts reached.']);
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

        $token = Str::random(60);
        auth()->user()->update(['exam_session_token' => $token]);
        session(['exam_session_token' => $token]);

        // Generate a per-student question order exactly ONCE at attempt creation.
        // Only shuffle when the teacher has enabled randomization for this exam.
        // Shuffle is server-side only; the client never sees or controls the order.
        $questionIds = $exam->questions()->orderBy('order')->pluck('id')->toArray();
        if ($exam->shuffle_questions) {
            shuffle($questionIds);
        }

        // ── Final Expiry = MIN(Start + Duration, Exam Open End) ──────────────
        // This enforces two independent teacher-controlled concepts:
        //   1. Exam Open Window  → $schedule->ends_at  (when students may start/be active)
        //   2. Exam Duration     → $schedule->duration_minutes (personal countdown per student)
        //
        // A student who starts late gets less effective time, but never more than
        // the open window allows.  Both guards are then enforced server-side on
        // every page load and inside the session recovery service.
        $durationExpiry  = now()->addMinutes($schedule->duration_minutes);
        $finalExpiry     = $durationExpiry->lessThan($schedule->ends_at)
                           ? $durationExpiry
                           : $schedule->ends_at->copy();

        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'schedule_id'    => $schedule->id,
            'student_id'     => auth()->id(),
            'attempt_number' => $attemptCount + 1,
            'status'         => 'in_progress',
            'started_at'     => now(),
            'expires_at'     => $finalExpiry,
            'session_token'  => $token,
            'question_order' => $questionIds,
        ]);

        return redirect()->route('student.exam.take', $attempt);
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\ExamAccessService;
use App\Services\ExamSecurityService;
use App\Services\GradingService;
use App\Services\SessionRecoveryService;
use Illuminate\Http\Request;

/**
 * ExamSessionController
 *
 * Handles the live exam session: page render, answer saving, violation
 * reporting, and final submission.
 *
 * Violation handling is fully delegated to ExamSecurityService.
 * Security policy flags are read from SecuritySetting::policy() and
 * passed to the exam view so the frontend enforces the configured policy.
 *
 * CheatingDetectionService is no longer injected here.
 * It is retained in the codebase as a read-only legacy component that
 * supports the existing /admin/cheating-logs view.
 */
class ExamSessionController extends Controller
{
    public function __construct(
        private ExamAccessService      $examAccess,
        private GradingService         $grading,
        private ExamSecurityService    $security,
        private SessionRecoveryService $recovery
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    //  Exam page
    // ──────────────────────────────────────────────────────────────────────

    public function take(ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $schedule = $attempt->schedule;

        // ── Expired recovery check ────────────────────────────────────────
        // If the attempt is in_progress AND has a disconnected_at timestamp,
        // the student was previously disconnected.
        // Handle reconnect: either restore the session or finalize it.
        if ($attempt->status === 'in_progress' && $attempt->disconnected_at !== null) {
            $result = $this->recovery->handleReconnect($attempt);

            if (! $result['success']) {
                // Recovery timed out — attempt is now submitted and graded.
                return redirect()->route('student.exams.show', $attempt->exam_id)
                    ->with('error', $result['message']);
            }

            // Recovery succeeded — refresh to pick up cleared disconnected_at.
            $attempt->refresh();
            session()->flash('info', $result['message']);

            // Use the frozen remaining seconds returned by the service.
            $finalAvailableSeconds = $result['frozen_seconds'] ?? 0;
            $effectiveEndsAt       = now()->addSeconds($finalAvailableSeconds);

            return $this->renderExamView($attempt, $schedule, $effectiveEndsAt);
        }

        // ── Timer-expiry guard ────────────────────────────────────────────
        if (now()->gt($attempt->expires_at)) {
            $this->submitAttempt($attempt);
            return redirect()->route('student.exams.show', $attempt->exam_id)
                ->with('success', 'Time expired. Exam auto-submitted.');
        }

        // ── Schedule-end guard ────────────────────────────────────────────
        if ($schedule && now()->gt($schedule->ends_at)) {
            $this->submitAttempt($attempt);
            return redirect()->route('student.exams.show', $attempt->exam_id)
                ->with('success', 'Exam schedule ended. Exam auto-submitted.');
        }

        // ── Normal path: compute available seconds and render ─────────────
        $finalAvailableSeconds = $this->recovery->computeNormalSeconds($attempt, $schedule);
        $effectiveEndsAt       = now()->addSeconds($finalAvailableSeconds);

        return $this->renderExamView($attempt, $schedule, $effectiveEndsAt);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Answer saving  (protected by EnsureExamActive middleware)
    // ──────────────────────────────────────────────────────────────────────

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $data = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id'   => 'nullable|exists:answers,id',
            'answer_text' => 'nullable|string',
            'answer_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $filePath = null;
        if ($request->hasFile('answer_file')) {
            $filePath = $request->file('answer_file')->store(
                "exams/{$attempt->exam_id}/attempts/{$attempt->id}",
                'public'
            );
        }

        StudentAnswer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
            [
                'answer_id'   => $data['answer_id']   ?? null,
                'answer_text' => $data['answer_text'] ?? null,
                'file_path'   => $filePath,
            ]
        );

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Violation reporting  (protected by EnsureExamActive middleware)
    // ──────────────────────────────────────────────────────────────────────

    public function violation(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $data = $request->validate([
            'type'    => 'required|string|max:80',
            'details' => 'nullable|string|max:500',
        ]);

        $result = $this->security->recordViolation(
            $attempt->fresh(),
            $data['type'],
            $data['details'] ?? null,
            [],
            $request->ip()
        );

        return response()->json($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Submission  (protected by EnsureExamActive middleware)
    // ──────────────────────────────────────────────────────────────────────

    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $this->submitAttempt($attempt);

        return redirect()->route('student.exams.show', $attempt->exam_id)
            ->with('success', 'Exam submitted successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Session disconnect handler  (for temporary interruptions)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Record a temporary disconnect event (browser close, network error).
     *
     * Status stays in_progress — a disconnect is NOT a termination.
     * Only disconnected_at and last_question_id are written.
     */
    public function disconnect(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json(['success' => false, 'message' => 'Attempt is not active'], 400);
        }

        $data = $request->validate([
            'question_id' => 'nullable|exists:questions,id',
            'reason'      => 'nullable|string|max:100',
        ]);

        $this->recovery->recordDisconnect(
            $attempt,
            $data['question_id'] ?? null,
            $data['reason'] ?? 'browser_close',
            [
                'user_agent'   => $request->userAgent(),
                'ip_address'   => $request->ip(),
                'browser_info' => [
                    'platform' => $request->header('sec-ch-ua-platform'),
                    'mobile'   => $request->header('sec-ch-ua-mobile'),
                ],
            ]
        );

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build and return the exam view.
     * Extracted to avoid duplication between normal and recovery code paths.
     */
    private function renderExamView(ExamAttempt $attempt, $schedule, $effectiveEndsAt): \Illuminate\View\View
    {
        $exam = $attempt->exam()->with(['questions.answers'])->first();

        if (! $this->examAccess->canDecryptQuestions(auth()->user(), $exam)) {
            abort(403, 'Questions are not available yet.');
        }

        // Apply the per-student question order saved at attempt creation.
        // If question_order is NULL (legacy attempt or edge case), fall back
        // to the natural DB order — grading is unaffected either way.
        $orderedQuestions = $exam->questions;
        if (! empty($attempt->question_order)) {
            $orderMap = array_flip($attempt->question_order);   // id → position
            $orderedQuestions = $orderedQuestions->sortBy(
                fn ($q) => $orderMap[$q->id] ?? PHP_INT_MAX
            )->values();
        }

        $questions = $orderedQuestions->map(function ($q) use ($exam) {
            return [
                'id'              => $q->id,
                'type'            => $q->type,
                'content'         => $this->examAccess->decryptContent(auth()->user(), $exam, $q->content_encrypted),
                'marks'           => $q->marks,
                'attachment_url'  => $q->hasAttachment() ? $q->attachmentUrl() : null,
                'attachment_name' => $q->attachment_name,
                'answers'         => $q->answers->map(fn ($a) => [
                    'id'      => $a->id,
                    'content' => $this->examAccess->decryptContent(auth()->user(), $exam, $a->content_encrypted),
                ]),
            ];
        });

        $savedAnswers = $attempt->studentAnswers()->pluck('answer_id', 'question_id');

        return view('student.exam.take', [
            'attempt'        => $attempt,
            'exam'           => $exam,
            'questions'      => $questions,
            'savedAnswers'   => $savedAnswers,
            'endsAt'         => $effectiveEndsAt->timestamp,
            'scheduleEndsAt' => $schedule ? $schedule->ends_at->timestamp : null,
            'securityPolicy' => [
                'fullscreen_detection_enabled'        => true,
                'blur_detection_enabled'              => true,
                'tab_switch_detection_enabled'        => true,
                'right_click_blocking_enabled'        => true,
                'copy_detection_enabled'              => true,
                'paste_detection_enabled'             => true,
                'devtools_detection_enabled'          => true,
                'keyboard_shortcut_detection_enabled' => true,
            ],
        ]);
    }

    private function submitAttempt(ExamAttempt $attempt): void
    {
        $attempt->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        auth()->user()->update(['exam_session_token' => null]);
        session()->forget('exam_session_token');

        $this->grading->gradeAttempt(
            $attempt->fresh(['studentAnswers.answer', 'studentAnswers.question'])
        );
    }

    private function authorizeAttempt(ExamAttempt $attempt): void
    {
        if ($attempt->student_id !== auth()->id()) {
            abort(403);
        }
    }
}

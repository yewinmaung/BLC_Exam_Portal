<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\CheatingDetectionService;
use App\Services\ExamAccessService;
use App\Services\GradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExamSessionController extends Controller
{
    public function __construct(
        private ExamAccessService $examAccess,
        private GradingService $grading,
        private CheatingDetectionService $cheating
    ) {
    }

    public function take(ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if (!$attempt->isActive()) {
            return redirect()->route('student.exams.show', $attempt->exam_id);
        }

        if (now()->gt($attempt->expires_at)) {
            $this->submitAttempt($attempt);

            return redirect()->route('student.exams.show', $attempt->exam_id)
                ->with('success', 'Time expired. Exam auto-submitted.');
        }

        $exam = $attempt->exam()->with(['questions.answers'])->first();

        if (!$this->examAccess->canDecryptQuestions(auth()->user(), $exam)) {
            abort(403, 'Questions are not available yet.');
        }

        $questions = $exam->questions->map(function ($q) use ($exam) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'content' => $this->examAccess->decryptContent(auth()->user(), $exam, $q->content_encrypted),
                'marks' => $q->marks,
                'attachment_url' => $q->hasAttachment() ? $q->attachmentUrl() : null,
                'attachment_name' => $q->attachment_name,
                'answers' => $q->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'content' => $this->examAccess->decryptContent(auth()->user(), $exam, $a->content_encrypted),
                ]),
            ];
        });

        $savedAnswers = $attempt->studentAnswers()->pluck('answer_id', 'question_id');

        return view('student.exam.take', [
            'attempt' => $attempt,
            'exam' => $exam,
            'questions' => $questions,
            'savedAnswers' => $savedAnswers,
            'endsAt' => $attempt->expires_at->timestamp,
        ]);
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if (!$attempt->isActive()) {
            return response()->json(['error' => 'Exam not active'], 403);
        }

        $data = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id' => 'nullable|exists:answers,id',
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
            [
                'attempt_id' => $attempt->id,
                'question_id' => $data['question_id'],
            ],
            [
                'answer_id' => $data['answer_id'] ?? null,
                'answer_text' => $data['answer_text'] ?? null,
                'file_path' => $filePath,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function violation(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $data = $request->validate([
            'type' => 'required|string',
            'details' => 'nullable|string',
        ]);

        $result = $this->cheating->recordViolation($attempt->fresh(), $data['type'], $data['details'] ?? null);

        return response()->json($result);
    }

    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $this->submitAttempt($attempt);

        return redirect()->route('student.exams.show', $attempt->exam_id)
            ->with('success', 'Exam submitted successfully.');
    }

    private function submitAttempt(ExamAttempt $attempt): void
    {
        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        auth()->user()->update(['exam_session_token' => null]);
        session()->forget('exam_session_token');

        $this->grading->gradeAttempt($attempt->fresh(['studentAnswers.answer', 'studentAnswers.question']));
    }

    private function authorizeAttempt(ExamAttempt $attempt): void
    {
        if ($attempt->student_id !== auth()->id()) {
            abort(403);
        }
    }
}

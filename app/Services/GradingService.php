<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Result;

class GradingService
{
    public function gradeAttempt(ExamAttempt $attempt): Result
    {
        // ── Guard: never overwrite a DISQUALIFIED result ──────────────────
        // If the result was already set to DISQUALIFIED (e.g., by ExamSecurityService
        // before this method was called), skip all grading so the final status
        // remains DISQUALIFIED. Return the existing record unchanged.
        $existing = Result::where('attempt_id', $attempt->id)->first();
        if ($existing && $existing->exam_result_status === Result::STATUS_DISQUALIFIED) {
            return $existing;
        }

        $exam = $attempt->exam;
        
        // ── STEP 1: Calculate total marks from ALL exam questions ─────────
        // Total marks = sum of all questions in the exam (regardless of answers)
        $allQuestions = $exam->questions;
        $totalMarks = $allQuestions->sum('marks');
        
        // ── STEP 2: Calculate obtained marks from answered questions ──────
        // Only count marks from questions that were answered
        // Unanswered questions automatically contribute 0 marks
        $obtainedMarks = 0;

        foreach ($attempt->studentAnswers as $studentAnswer) {
            $question = $studentAnswer->question;

            if (in_array($question->type, ['mcq', 'true_false'], true)) {
                $correct = $studentAnswer->answer && $studentAnswer->answer->is_correct;
                $marks = $correct ? $question->marks : 0;
                $studentAnswer->update([
                    'is_correct' => $correct,
                    'marks_awarded' => $marks,
                ]);
                $obtainedMarks += $marks;

            } elseif ($question->type === 'fill_blank') {
                // Compare student text answer against all accepted blank answers (case-insensitive)
                $studentText = trim(strtolower($studentAnswer->answer_text ?? ''));
                $acceptedAnswers = $question->answers()
                    ->where('is_blank_answer', true)
                    ->get()
                    ->map(fn ($a) => strtolower(trim($a->decrypted_content ?? '')));

                $correct = $studentText !== '' && $acceptedAnswers->contains($studentText);
                $marks   = $correct ? $question->marks : 0;
                $studentAnswer->update([
                    'is_correct'    => $correct,
                    'marks_awarded' => $marks,
                ]);
                $obtainedMarks += $marks;
            }
        }

        // ── STEP 3: Calculate percentage using full exam marks ────────────
        // Formula: Percentage = (Obtained Marks / Total Marks) × 100
        // Example: 4 obtained / 10 total = 40%
        $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        
        // ── STEP 4: Determine pass/fail based on passing marks threshold ──
        $isPassed = $obtainedMarks >= $exam->passing_marks;

        // ── STEP 5: Save result WITHOUT grade calculation ─────────────────
        return Result::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'exam_id'             => $exam->id,
                'student_id'          => $attempt->student_id,
                'total_marks'         => $totalMarks,
                'obtained_marks'      => $obtainedMarks,
                'percentage'          => $percentage,
                // REMOVED: grade calculation completely removed
                'is_passed'           => $isPassed,
                'is_published'        => true,
                // ── Result status extension ───────────────────────────────
                // Note: If cheating is detected, ExamSecurityService will set
                // exam_result_status to DISQUALIFIED and is_passed to false
                // while keeping the actual obtained_marks and percentage intact.
                'exam_result_status'  => $isPassed ? Result::STATUS_PASSED : Result::STATUS_FAILED,
                'attendance_status'   => Result::ATTENDANCE_ATTENDED,
                'exam_finished_at'    => $attempt->submitted_at ?? now(),
            ]
        );
    }
}

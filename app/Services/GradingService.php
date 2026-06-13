<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Result;

class GradingService
{
    public function gradeAttempt(ExamAttempt $attempt): Result
    {
        $exam = $attempt->exam;
        $totalMarks = 0;
        $obtainedMarks = 0;

        foreach ($attempt->studentAnswers as $studentAnswer) {
            $question = $studentAnswer->question;
            $totalMarks += $question->marks;

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

        $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $isPassed = $obtainedMarks >= $exam->passing_marks;

        return Result::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'exam_id' => $exam->id,
                'student_id' => $attempt->student_id,
                'total_marks' => $totalMarks,
                'obtained_marks' => $obtainedMarks,
                'percentage' => $percentage,
                'grade' => $this->calculateGrade($percentage),
                'is_passed' => $isPassed,
                'is_published' => true,
            ]
        );
    }

    private function calculateGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'F',
        };
    }
}

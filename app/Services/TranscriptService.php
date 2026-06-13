<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Models\YearlyExamResult;
use App\Models\YearlyTranscript;
use Barryvdh\DomPDF\Facade\Pdf;

class TranscriptService
{
    /**
     * Generate and store a transcript record for a student.
     */
    public function generate(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $semester,
        User $generatedBy
    ): YearlyTranscript {
        $results = YearlyExamResult::where([
            'student_id'       => $student->id,
            'academic_year_id' => $academicYear->id,
            'year_level_id'    => $yearLevel->id,
            'semester'         => $semester,
        ])->get();

        $totalMarks    = $results->sum('total_marks');
        $obtainedMarks = $results->sum('obtained_marks');
        $percentage    = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $gpa           = round($percentage / 25, 2);
        $grade         = $this->gradeFromPercentage($percentage);
        $isPassed      = $percentage >= 50;

        return YearlyTranscript::updateOrCreate(
            [
                'student_id'       => $student->id,
                'academic_year_id' => $academicYear->id,
                'year_level_id'    => $yearLevel->id,
                'semester'         => $semester,
            ],
            [
                'gpa'            => $gpa,
                'total_marks'    => $totalMarks,
                'obtained_marks' => $obtainedMarks,
                'percentage'     => $percentage,
                'grade'          => $grade,
                'is_passed'      => $isPassed,
                'status'         => 'published',
                'generated_by'   => $generatedBy->id,
            ]
        );
    }

    /**
     * Export transcript as PDF.
     */
    public function exportPdf(User $student, AcademicYear $academicYear): \Illuminate\Http\Response
    {
        $records  = StudentYearRecord::with(['yearLevel', 'academicYear'])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $results = YearlyExamResult::with(['exam'])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $transcript = YearlyTranscript::where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->first();

        $pdf = Pdf::loadView('pdf.transcript', compact('student', 'academicYear', 'records', 'results', 'transcript'))
            ->setPaper('a4', 'portrait');

        $filename = 'transcript_' . $student->id . '_' . $academicYear->name . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get full academic history for a student across all years.
     */
    public function getHistory(User $student): array
    {
        $records = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $history = [];
        foreach ($records as $record) {
            $results = YearlyExamResult::with(['exam'])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $record->academic_year_id)
                ->where('year_level_id', $record->year_level_id)
                ->get();

            $transcript = YearlyTranscript::where([
                'student_id'       => $student->id,
                'academic_year_id' => $record->academic_year_id,
                'year_level_id'    => $record->year_level_id,
            ])->first();

            $history[] = compact('record', 'results', 'transcript');
        }

        return $history;
    }

    private function gradeFromPercentage(float $pct): string
    {
        return match (true) {
            $pct >= 80 => 'A',
            $pct >= 70 => 'B',
            $pct >= 60 => 'C',
            $pct >= 50 => 'D',
            default    => 'F',
        };
    }
}

<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Result;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;

class AcademicService
{
    /**
     * Enroll a student into an academic year + year level.
     */
    public function enrollStudent(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $semester = '1',
        ?string $department = null,
        ?string $major = null
    ): StudentYearRecord {
        return StudentYearRecord::firstOrCreate(
            [
                'student_id'       => $student->id,
                'academic_year_id' => $academicYear->id,
                'year_level_id'    => $yearLevel->id,
                'semester'         => $semester,
            ],
            [
                'department' => $department,
                'major'      => $major,
                'status'     => 'active',
            ]
        );
    }

    /**
     * Get academic history for a student across all year records,
     * using live exam results scoped to each record's academic year / year level / semester.
     */
    public function getStudentHistory(User $student): array
    {
        $records = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $history = [];
        foreach ($records as $record) {
            $yearLevel = $record->yearLevel?->level;
            $semester  = (int) $record->semester;

            $results = Result::with([
                    'exam.course',
                    'exam.questions.answers',
                    'attempt.studentAnswers.answer',
                ])
                ->where('student_id', $student->id)
                ->where('is_published', true)
                ->whereHas('exam.schedules', fn ($sq) => $sq->where('ends_at', '<=', now()))
                ->whereHas('exam.course', function ($c) use ($student, $record, $yearLevel, $semester) {
                    $c->where('academic_year_id', $record->academic_year_id)
                        ->whereHas('enrollments', fn ($e) => $e->where('student_id', $student->id));

                    // year_level 0 = all years; otherwise match this record's level
                    if ($yearLevel) {
                        $c->where(function ($q) use ($yearLevel) {
                            $q->where('year_level', 0)
                              ->orWhere('year_level', $yearLevel);
                        });
                    }

                    // semester 0 = both; otherwise match this record's semester
                    $c->where(function ($q) use ($semester) {
                        $q->where('semester', 0)
                          ->orWhere('semester', $semester);
                    });
                })
                ->latest()
                ->get();

            $history[] = [
                'record'  => $record,
                'results' => $results,
            ];
        }

        return $history;
    }
}
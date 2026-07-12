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
     * using live exam results (no transcript archiving needed).
     */
    public function getStudentHistory(User $student): array
    {
        $records = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $history = [];
        foreach ($records as $record) {
            // Use live results for display
            $results = Result::with(['exam.course'])
                ->where('student_id', $student->id)
                ->whereHas('exam', fn ($q) =>
                    $q->whereHas('course.enrollments', fn ($e) =>
                        $e->where('student_id', $student->id)
                    )
                )
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

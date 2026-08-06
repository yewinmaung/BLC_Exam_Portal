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
     * Get academic history for a student across all year records.
     *
     * For each StudentYearRecord, results are found by matching the course's
     * year_level and semester against the record — NOT by courses.academic_year_id
     * and NOT by date windows, because those are unreliable when courses are
     * reused or exam schedules use arbitrary dates.
     *
     * Match logic (same priority order as ResultController):
     *   1. course.year_level == record level AND course.semester == record semester
     *   2. course.year_level == record level (course covers both semesters)
     *   3. course.semester == record semester (course covers all year levels)
     *   4. Any course the student is enrolled in (last resort)
     */
    public function getStudentHistory(User $student): array
    {
        $records = StudentYearRecord::with(['academicYear', 'yearLevel'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id')
            ->get();

        $history = [];

        foreach ($records as $record) {
            $recordYl  = $record->yearLevel?->level;  // int 1–5 or null
            $recordSem = (int) $record->semester;      // 0 = both, 1, or 2

            // Find results for courses that match this record's year_level + semester.
            // We do NOT filter by courses.academic_year_id — courses can be reused.
            // Instead we match on structural metadata (year level + semester) which
            // uniquely identifies which cohort/period a course belongs to for this student.
            $results = Result::with([
                    'exam.course',
                    'exam.questions.answers',
                    'attempt.studentAnswers.answer',
                ])
                ->where('student_id', $student->id)
                ->where('is_published', true)
                // Exam schedule must have ended
                ->whereHas('exam.schedules', fn ($sq) =>
                    $sq->where('ends_at', '<=', now())
                )
                // Student must be enrolled in the course
                ->whereHas('exam.course.enrollments', fn ($e) =>
                    $e->where('student_id', $student->id)
                )
                // Match course to this record's year level (0 on course = all years)
                ->when($recordYl, fn ($q) =>
                    $q->whereHas('exam.course', fn ($c) =>
                        $c->where(fn ($wl) =>
                            $wl->where('year_level', 0)->orWhere('year_level', $recordYl)
                        )
                    )
                )
                // Match course to this record's semester (0 on course = both semesters;
                // 0 on record = both semesters, so no restriction needed)
                ->when($recordSem !== 0, fn ($q) =>
                    $q->whereHas('exam.course', fn ($c) =>
                        $c->where(fn ($s) =>
                            $s->where('semester', 0)->orWhere('semester', $recordSem)
                        )
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
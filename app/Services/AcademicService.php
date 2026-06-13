<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CertificateLog;
use App\Models\PromotionHistory;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Models\YearlyExamResult;
use Illuminate\Support\Str;

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
     * Archive exam results into the permanent yearly_exam_results table.
     */
    public function archiveResults(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $semester = '1'
    ): int {
        // Get all results for this student in this academic year
        $results = \App\Models\Result::where('student_id', $student->id)
            ->with('exam')
            ->get();

        $count = 0;
        foreach ($results as $result) {
            YearlyExamResult::updateOrCreate(
                [
                    'student_id'       => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'year_level_id'    => $yearLevel->id,
                    'exam_id'          => $result->exam_id,
                    'semester'         => $semester,
                ],
                [
                    'result_id'      => $result->id,
                    'obtained_marks' => $result->obtained_marks,
                    'total_marks'    => $result->total_marks,
                    'percentage'     => $result->percentage,
                    'grade'          => $result->grade,
                    'is_passed'      => $result->is_passed,
                ]
            );
            $count++;
        }

        // Calculate and store GPA
        $this->recalculateGpa($student, $academicYear, $yearLevel, $semester);

        return $count;
    }

    /**
     * Recalculate and store GPA for a student year record.
     */
    public function recalculateGpa(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $semester = '1'
    ): float {
        $results = YearlyExamResult::where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('year_level_id', $yearLevel->id)
            ->where('semester', $semester)
            ->get();

        $gpa = $results->count() > 0
            ? round($results->avg('percentage') / 25, 2)  // 100% = 4.0 GPA
            : 0.0;

        StudentYearRecord::where([
            'student_id'       => $student->id,
            'academic_year_id' => $academicYear->id,
            'year_level_id'    => $yearLevel->id,
            'semester'         => $semester,
        ])->update(['gpa' => $gpa]);

        return $gpa;
    }

    /**
     * Promote a student to the next year level.
     */
    public function promoteStudent(
        User $student,
        AcademicYear $academicYear,
        YearLevel $fromLevel,
        YearLevel $toLevel,
        User $promotedBy,
        ?string $notes = null
    ): PromotionHistory {
        // Mark current record as promoted
        StudentYearRecord::where([
            'student_id'       => $student->id,
            'academic_year_id' => $academicYear->id,
            'year_level_id'    => $fromLevel->id,
        ])->update(['status' => 'promoted', 'promoted_at' => now()]);

        // Record promotion history (permanent)
        return PromotionHistory::create([
            'student_id'          => $student->id,
            'from_year_level_id'  => $fromLevel->id,
            'to_year_level_id'    => $toLevel->id,
            'academic_year_id'    => $academicYear->id,
            'promoted_by'         => $promotedBy->id,
            'notes'               => $notes,
            'promoted_at'         => now(),
        ]);
    }

    /**
     * Generate a unique certificate serial number.
     */
    public function generateSerial(string $type = 'CERT'): string
    {
        $year  = now()->year;
        $count = CertificateLog::whereYear('issued_at', $year)->count() + 1;
        return strtoupper($type) . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Issue a certificate and log it.
     */
    public function issueCertificate(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $type,
        string $issuedBy,
        User $createdBy
    ): CertificateLog {
        return CertificateLog::create([
            'serial_number'    => $this->generateSerial('CERT'),
            'student_id'       => $student->id,
            'academic_year_id' => $academicYear->id,
            'year_level_id'    => $yearLevel->id,
            'type'             => $type,
            'issued_by'        => $issuedBy,
            'qr_token'         => Str::uuid()->toString(),
            'issued_at'        => now(),
            'created_by'       => $createdBy->id,
        ]);
    }

    /**
     * Get full academic history for a student (all years, permanent).
     */
    public function getStudentHistory(User $student): array
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

            $history[] = [
                'record'  => $record,
                'results' => $results,
            ];
        }

        return $history;
    }
}

<?php

namespace App\Services;

use App\Models\Major;
use App\Models\StudentYearRecord;
use App\Models\YearLevel;

/**
 * Enforces major specialization lock rules:
 *
 * - Year 1: no specialization (handled elsewhere).
 * - Year 2+ entry: student picks major once (normal Y2, or transfer entry year).
 * - Once the student progresses beyond that entry year level, the first
 *   Year 2+ major becomes the canonical lock for all higher records.
 */
class StudentMajorLockService
{
    /**
     * Earliest Year 2+ record by academic year (specialization entry point).
     */
    public function getSpecializationRecord(int $studentId): ?StudentYearRecord
    {
        return StudentYearRecord::query()
            ->where('student_year_records.student_id', $studentId)
            ->join('year_levels', 'year_levels.id', '=', 'student_year_records.year_level_id')
            ->join('academic_years', 'academic_years.id', '=', 'student_year_records.academic_year_id')
            ->where('year_levels.level', '>=', 2)
            ->select('student_year_records.*')
            ->orderBy('academic_years.start_year')
            ->orderBy('student_year_records.id')
            ->first();
    }

    public function getCanonicalMajorId(int $studentId): ?int
    {
        $record = $this->getSpecializationRecord($studentId);

        if (!$record || empty($record->major)) {
            return null;
        }

        return Major::resolveIdFromLabel($record->major);
    }

    /**
     * Whether the submitted major must match the canonical specialization major.
     */
    public function isMajorLocked(int $studentId, int $targetYearLevel): bool
    {
        if ($targetYearLevel < 2) {
            return false;
        }

        $entryRecord = $this->getSpecializationRecord($studentId);
        if (!$entryRecord || empty($entryRecord->major)) {
            return false;
        }

        $entryLevel = (int) (YearLevel::find($entryRecord->year_level_id)?->level ?? 0);

        if ($targetYearLevel >= 3 && $entryLevel < $targetYearLevel) {
            return true;
        }

        if ($targetYearLevel === 2 && $this->hasRecordAboveLevel($studentId, 2)) {
            return true;
        }

        if ($targetYearLevel >= 3
            && $entryLevel === $targetYearLevel
            && $this->hasRecordAboveLevel($studentId, $targetYearLevel)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return string|null Error message when invalid; null when valid.
     */
    public function validateMajor(
        int $studentId,
        int $targetYearLevel,
        ?int $submittedMajorId
    ): ?string {
        if ($targetYearLevel < 2 || !$this->isMajorLocked($studentId, $targetYearLevel)) {
            return null;
        }

        $canonicalId = $this->getCanonicalMajorId($studentId);
        if ($canonicalId === null) {
            return 'Major is required. Set the specialization major on the student\'s entry year record first.';
        }

        if ((int) $submittedMajorId !== (int) $canonicalId) {
            $code = Major::find($canonicalId)?->code ?? 'their specialization major';

            return "Major cannot be changed after specialization. "
                 . "This student must remain on {$code} (chosen on their entry year).";
        }

        return null;
    }

    /**
     * Resolve the major_id that should be persisted for this save operation.
     */
    public function resolveMajorIdForSave(
        int $studentId,
        int $targetYearLevel,
        ?int $submittedMajorId
    ): ?int {
        if ($this->isMajorLocked($studentId, $targetYearLevel)) {
            return $this->getCanonicalMajorId($studentId);
        }

        return $submittedMajorId;
    }

    private function hasRecordAboveLevel(int $studentId, int $level): bool
    {
        return StudentYearRecord::query()
            ->where('student_year_records.student_id', $studentId)
            ->join('year_levels', 'year_levels.id', '=', 'student_year_records.year_level_id')
            ->where('year_levels.level', '>', $level)
            ->exists();
    }
}

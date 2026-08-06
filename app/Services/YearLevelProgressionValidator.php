<?php

namespace App\Services;

use App\Enums\RecordType;
use App\Models\StudentYearRecord;
use App\Models\YearLevel;

/**
 * Validates year-level progression rules for StudentYearRecord.
 *
 * ── Semantics ────────────────────────────────────────────────────────────────
 *
 * record_type is a *per-record label*, not a permanent student classification.
 *
 * NORMAL (default / null)
 *   - The student's very first record must be First Year (level 1).
 *   - Academic years must be consecutive — no gaps allowed.
 *     A gap (e.g. 2018-2019 → 2023-2024) requires READMISSION, not NORMAL.
 *   - Year level must increase exactly +1 each record.
 *   - No duplicate year levels or duplicate academic years.
 *
 * TRANSFER
 *   - Only allowed as the student's very first record in this system.
 *     → If the student already has ANY records, TRANSFER is rejected.
 *   - That first record may be any year level (flexible entry point).
 *   - The academic year immediately after TRANSFER must be consecutive (+1 start_year).
 *   - All subsequent records follow NORMAL progression rules.
 *   - No duplicate year levels or duplicate academic years.
 *
 * READMISSION
 *   - Marks a re-entry record after an academic gap.
 *   - May NOT be the student's first record — prior records must already exist.
 *   - Allows an academic year gap before the READMISSION record (that is its purpose).
 *   - Year level must still be exactly +1 from the previous record — no skipping.
 *   - After READMISSION, the next record must be consecutive NORMAL or another
 *     READMISSION if another gap occurs.
 *   - Multiple READMISSION records are allowed (one per gap).
 *   - No duplicate year levels or duplicate academic years.
 *
 * ── Full timeline validation ─────────────────────────────────────────────────
 *
 * The timeline is sorted chronologically by start_year, then validated pair-by-pair:
 *
 *   For each consecutive pair (prev → curr):
 *     A. Year level must be exactly prev.level + 1 (all types, no exceptions).
 *     B. Academic year must be consecutive (curr.start_year == prev.start_year + 1)
 *        UNLESS curr.record_type is READMISSION (gap is permitted before READMISSION).
 *
 *   Start-level rule:
 *     - TRANSFER as first → any starting level ok.
 *     - NORMAL or READMISSION as first → must start at level 1.
 *       (READMISSION as first is blocked before this point.)
 *
 * ── Usage ────────────────────────────────────────────────────────────────────
 *
 * CREATE → validate($studentId, $newLevel, $newAcademicYearId, $recordType)
 * EDIT   → validateEdit($studentId, $newLevel, $newAcademicYearId, $recordType, $editingRecordId)
 */
class YearLevelProgressionValidator
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Validate adding a brand-new record (CREATE path).
     *
     * @param  int         $studentId         Student user ID.
     * @param  int         $newLevel          Proposed year-level integer (1–5).
     * @param  int         $newAcademicYearId Proposed academic_year_id.
     * @param  string|null $recordType        RecordType::* or null (treated as NORMAL).
     * @return string|null Null = valid; string = error message.
     */
    public function validate(
        int $studentId,
        int $newLevel,
        int $newAcademicYearId,
        ?string $recordType
    ): ?string {
        $existing = $this->getExistingEntries($studentId, null);

        // Build proposed final timeline and validate it.
        $proposed = [
            'level'            => $newLevel,
            'academic_year_id' => $newAcademicYearId,
            'start_year'       => $this->resolveStartYear($newAcademicYearId),
            'record_type'      => $recordType ?? RecordType::NORMAL,
        ];

        return $this->validateFinalTimeline($existing, $proposed);
    }

    /**
     * Validate editing an existing record (UPDATE path).
     *
     * Removes the record being edited, merges in the proposed values, and
     * validates the entire resulting timeline.
     *
     * @param  int         $studentId         Student user ID.
     * @param  int         $newLevel          Proposed year-level integer.
     * @param  int         $newAcademicYearId Proposed academic_year_id.
     * @param  string|null $recordType        RecordType::* or null.
     * @param  int         $editingRecordId   PK of the record being edited.
     * @return string|null Null = valid; string = error message.
     */
    public function validateEdit(
        int $studentId,
        int $newLevel,
        int $newAcademicYearId,
        ?string $recordType,
        int $editingRecordId
    ): ?string {
        $others = $this->getExistingEntries($studentId, $editingRecordId);

        $proposed = [
            'level'            => $newLevel,
            'academic_year_id' => $newAcademicYearId,
            'start_year'       => $this->resolveStartYear($newAcademicYearId),
            'record_type'      => $recordType ?? RecordType::NORMAL,
        ];

        return $this->validateFinalTimeline($others, $proposed);
    }

    // ── Core validation ───────────────────────────────────────────────────────

    /**
     * Validate the full timeline that would result from adding $proposed to $others.
     *
     * Each entry shape: ['level', 'academic_year_id', 'start_year', 'record_type']
     *
     * $others   – existing entries (excluding the record being edited on UPDATE path).
     * $proposed – the new/edited entry in the same shape.
     */
    private function validateFinalTimeline(array $others, array $proposed): ?string
    {
        $newLevel          = $proposed['level'];
        $newAcademicYearId = $proposed['academic_year_id'];
        $newType           = $proposed['record_type'];

        // ── 1. Duplicate academic year ────────────────────────────────────────
        foreach ($others as $e) {
            if ((int) $e['academic_year_id'] === (int) $newAcademicYearId) {
                return "The student already has a record for this academic year.";
            }
        }

        // ── 2. Duplicate year level ───────────────────────────────────────────
        foreach ($others as $e) {
            if ((int) $e['level'] === $newLevel) {
                $name = YearLevel::$names[$newLevel] ?? "Year {$newLevel}";
                return "The student already has a record for {$name}.";
            }
        }

        // ── 3. TRANSFER-specific rule: only allowed on the FIRST record ───────
        if ($newType === RecordType::TRANSFER && !empty($others)) {
            return "Transfer can only be used on a student's first academic record in this university. "
                 . "This student already has existing academic records.";
        }

        // ── 4. READMISSION-specific rule: cannot be the first record ──────────
        if ($newType === RecordType::READMISSION && empty($others)) {
            return "Re-admission cannot be used on a student's first academic record. "
                 . "The student must have prior academic records before re-admission.";
        }

        // ── 5. Build the full final timeline sorted chronologically ───────────
        // Sort by start_year so we validate in actual academic-year order.
        $all   = $others;
        $all[] = $proposed;
        usort($all, fn ($a, $b) => (int) $a['start_year'] <=> (int) $b['start_year']);

        // ── 6. Determine start-level rule ─────────────────────────────────────
        // TRANSFER as first → any starting level ok (flexible entry point).
        // NORMAL as first   → must start at level 1.
        // (READMISSION as first is already blocked by rule #4 above.)
        $firstEntry      = $all[0];
        $firstType       = $firstEntry['record_type'] ?? RecordType::NORMAL;
        $isFlexibleStart = $firstType === RecordType::TRANSFER;

        if (!$isFlexibleStart && (int) $firstEntry['level'] !== 1) {
            $foundName = YearLevel::$names[(int) $firstEntry['level']] ?? "Year {$firstEntry['level']}";
            return "A student without a Transfer record must start with First Year, "
                 . "but the earliest record is {$foundName}. "
                 . "Set the Student Type to Transfer to allow starting at a higher year level.";
        }

        // ── 7. Pair-by-pair progression check ────────────────────────────────
        //
        // For each consecutive pair (prev → curr):
        //
        //   A. Year level must be exactly prev.level + 1  (all types, no exceptions).
        //
        //   B. Academic year must be consecutive (curr.start_year == prev.start_year + 1)
        //      UNLESS curr.record_type is READMISSION — a gap is the reason for re-admission.
        //      TRANSFER is only ever the first record so it never appears as "curr" here.
        //
        for ($i = 1; $i < count($all); $i++) {
            $prev     = $all[$i - 1];
            $curr     = $all[$i];
            $prevLevel = (int) $prev['level'];
            $currLevel = (int) $curr['level'];
            $prevYear  = (int) $prev['start_year'];
            $currYear  = (int) $curr['start_year'];
            $currType  = $curr['record_type'] ?? RecordType::NORMAL;

            // A. Year level must advance exactly +1.
            if ($currLevel !== $prevLevel + 1) {
                return $this->sequenceErrorMessage($prevLevel, $currLevel, $prevLevel + 1);
            }

            // B. Academic year must be consecutive for NORMAL records.
            //    READMISSION is explicitly allowed to have a gap before it.
            if ($currType !== RecordType::READMISSION && $currYear !== $prevYear + 1) {
                $prevAyName = ($prevYear . '-' . ($prevYear + 1));
                $currAyName = ($currYear . '-' . ($currYear + 1));
                return "Academic years must be consecutive for Normal records. "
                     . "Expected " . ($prevYear + 1) . "-" . ($prevYear + 2) . " after {$prevAyName}, "
                     . "but got {$currAyName}. "
                     . "Use Re-admission to record a return after an academic year gap.";
            }
        }

        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sequenceErrorMessage(int $prevLevel, int $gotLevel, int $expectedLevel): string
    {
        $prevName     = YearLevel::$names[$prevLevel]     ?? "Year {$prevLevel}";
        $expectedName = YearLevel::$names[$expectedLevel] ?? "Year {$expectedLevel}";
        $gotName      = YearLevel::$names[$gotLevel]      ?? "Year {$gotLevel}";

        return "Year levels must progress sequentially. "
             . "Expected {$expectedName} (the next level after {$prevName}), "
             . "but got {$gotName}.";
    }

    /**
     * Resolve the start_year integer for a given academic_year_id.
     * Falls back to 0 if the academic year cannot be found.
     */
    private function resolveStartYear(int $academicYearId): int
    {
        return (int) (\App\Models\AcademicYear::find($academicYearId)?->start_year ?? 0);
    }

    /**
     * Fetch all existing StudentYearRecord entries for the student as structured arrays,
     * optionally excluding one record by its PK (used for edit).
     *
     * @return array<int, array{level: int, academic_year_id: int, start_year: int, record_type: string}>
     */
    private function getExistingEntries(int $studentId, ?int $excludeRecordId): array
    {
        $query = StudentYearRecord::where('student_year_records.student_id', $studentId)
            ->join('year_levels',    'year_levels.id',    '=', 'student_year_records.year_level_id')
            ->join('academic_years', 'academic_years.id', '=', 'student_year_records.academic_year_id')
            ->select(
                'year_levels.level',
                'student_year_records.academic_year_id',
                'academic_years.start_year',
                'student_year_records.record_type'
            );

        if ($excludeRecordId !== null) {
            $query->where('student_year_records.id', '!=', $excludeRecordId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'level'            => (int) $row->level,
                'academic_year_id' => (int) $row->academic_year_id,
                'start_year'       => (int) $row->start_year,
                'record_type'      => $row->record_type ?? RecordType::NORMAL,
            ])
            ->all();
    }
}

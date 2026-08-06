<?php

namespace App\Enums;

/**
 * Allowed values for student_year_records.record_type.
 * NULL is treated as NORMAL for backward compatibility.
 */
class RecordType
{
    public const NORMAL      = 'NORMAL';
    public const TRANSFER    = 'TRANSFER';
    public const READMISSION = 'READMISSION';

    /** All valid string values accepted by the UI / validation. */
    public const ALL = [self::NORMAL, self::TRANSFER, self::READMISSION];

    /** Human-readable labels for dropdowns. */
    public const LABELS = [
        self::NORMAL      => 'Normal',
        self::TRANSFER    => 'Transfer',
        self::READMISSION => 'Re-admission',
    ];
}

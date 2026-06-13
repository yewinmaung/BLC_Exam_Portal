<?php

namespace App\Support;

class AcademicYear
{
    public const OPTIONS = [
        1 => 'Year 1',
        2 => 'Year 2',
        3 => 'Year 3',
        4 => 'Year 4',
        5 => 'Year 5',
    ];

    public static function label(?int $year): string
    {
        return $year ? (self::OPTIONS[$year] ?? "Year {$year}") : '—';
    }
}

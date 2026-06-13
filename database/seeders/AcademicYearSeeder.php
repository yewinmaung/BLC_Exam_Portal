<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        // Create current academic year 2026-2027 if not exists
        AcademicYear::firstOrCreate(
            ['name' => '2026-2027'],
            [
                'start_year' => 2026,
                'end_year' => 2027,
                'is_current' => true,
            ]
        );

        // Create previous academic years
        AcademicYear::firstOrCreate(
            ['name' => '2025-2026'],
            [
                'start_year' => 2025,
                'end_year' => 2026,
                'is_current' => false,
            ]
        );

        AcademicYear::firstOrCreate(
            ['name' => '2024-2025'],
            [
                'start_year' => 2024,
                'end_year' => 2025,
                'is_current' => false,
            ]
        );
    }
}
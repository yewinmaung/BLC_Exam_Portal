<?php

namespace Database\Seeders;

use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class YearLevelSeeder extends Seeder
{
    public function run(): void
    {
        $yearLevels = [
            ['level' => 1, 'name' => 'First Year'],
            ['level' => 2, 'name' => 'Second Year'],
            ['level' => 3, 'name' => 'Third Year'],
            ['level' => 4, 'name' => 'Fourth Year'],
            ['level' => 5, 'name' => 'Final Year'],
        ];

        foreach ($yearLevels as $level) {
            YearLevel::firstOrCreate(
                ['level' => $level['level']],
                ['name' => $level['name']]
            );
        }
    }
}
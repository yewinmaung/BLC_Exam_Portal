<?php

namespace Database\Seeders;

use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QuestionCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['General Knowledge', 'Mathematics', 'Science', 'Programming', 'English'];

        foreach ($categories as $name) {
            QuestionCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => "{$name} questions"]
            );
        }
    }
}

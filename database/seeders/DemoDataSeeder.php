<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@believeexam.com')->first();
        $student = User::where('email', 'student@believeexam.com')->first();
        $admin = User::where('email', 'admin@believeexam.com')->first();

        if (!$teacher || !$student) {
            return;
        }

        $course = Course::firstOrCreate(
            ['code' => 'CS101'],
            [
                'title' => 'Introduction to Computer Science',
                'description' => 'Demo course for Believe Exam system.',
                'teacher_id' => $teacher->id,
                'created_by' => $admin?->id,
            ]
        );

        Enrollment::firstOrCreate([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
    }
}

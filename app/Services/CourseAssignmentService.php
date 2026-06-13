<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class CourseAssignmentService
{
    public function syncCourseStudents(Course $course, array $studentIds): void
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        $query = Enrollment::where('course_id', $course->id);
        if ($studentIds !== []) {
            $query->whereNotIn('student_id', $studentIds);
        }
        $query->delete();

        foreach ($studentIds as $studentId) {
            $student = User::find($studentId);
            if (!$student?->isStudent()) {
                continue;
            }

            $year = (int) ($student->academic_year ?? 1);

            Enrollment::updateOrCreate(
                [
                    'course_id'  => $course->id,
                    'student_id' => $studentId,
                    'year'       => $year,
                ],
                ['enrolled_at' => now()]
            );
        }
    }

    public function syncTeacherCourses(User $teacher, array $courseIds): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));

        Course::where('teacher_id', $teacher->id)
            ->when($courseIds !== [], fn ($q) => $q->whereNotIn('id', $courseIds))
            ->update(['teacher_id' => null]);

        if ($courseIds !== []) {
            Course::whereIn('id', $courseIds)->update(['teacher_id' => $teacher->id]);
        }
    }

    public function syncStudentCourses(User $student, array $courseIds): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        $year = (int) ($student->academic_year ?? 1);

        $query = Enrollment::where('student_id', $student->id);
        if ($courseIds !== []) {
            $query->whereNotIn('course_id', $courseIds);
        }
        $query->delete();

        foreach ($courseIds as $courseId) {
            Enrollment::updateOrCreate(
                [
                    'course_id'  => $courseId,
                    'student_id' => $student->id,
                    'year'       => $year,
                ],
                ['enrolled_at' => now()]
            );
        }
    }
}

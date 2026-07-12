<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;

class CourseAssignmentService
{
    /**
     * Sync the enrolled students of a course.
     * Removes students not in $studentIds, adds/updates the rest.
     */
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

            // Resolve year level from the student's active record where possible
            [$yearInt, $yearLevelId, $majorId] = $this->resolveStudentAcademicContext($student, $course);

            Enrollment::updateOrCreate(
                [
                    'course_id'  => $course->id,
                    'student_id' => $studentId,
                    'year'       => $yearInt,
                ],
                [
                    'year_level_id' => $yearLevelId,
                    'major_id'      => $majorId,
                    'enrolled_at'   => now(),
                ]
            );
        }
    }

    /**
     * Sync teacher → course assignments (sets/clears teacher_id on courses).
     */
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

    /**
     * Sync courses a student is enrolled in.
     * Preserves year_level_id / major_id derived from the student's active record.
     */
    public function syncStudentCourses(User $student, array $courseIds): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));

        $query = Enrollment::where('student_id', $student->id);
        if ($courseIds !== []) {
            $query->whereNotIn('course_id', $courseIds);
        }
        $query->delete();

        foreach ($courseIds as $courseId) {
            $course = Course::find($courseId);
            if (!$course) {
                continue;
            }

            [$yearInt, $yearLevelId, $majorId] = $this->resolveStudentAcademicContext($student, $course);

            Enrollment::updateOrCreate(
                [
                    'course_id'  => $courseId,
                    'student_id' => $student->id,
                    'year'       => $yearInt,
                ],
                [
                    'year_level_id' => $yearLevelId,
                    'major_id'      => $majorId,
                    'enrolled_at'   => now(),
                ]
            );
        }
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Returns [yearInt, yearLevelId, majorId] for a student/course pair,
     * using the student's active StudentYearRecord as the source of truth.
     *
     * Falls back to the legacy `academic_year` integer on users if no record exists.
     */
    private function resolveStudentAcademicContext(User $student, Course $course): array
    {
        $record = StudentYearRecord::where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($record) {
            $yearLevelId = $record->year_level_id;
            $yearLevel   = YearLevel::find($yearLevelId);
            $yearInt     = $yearLevel?->level ?? (int) ($student->academic_year ?? 1);

            // major_id: only relevant for Year 2+ and when course declares a major
            $majorId = null;
            if ($yearInt >= 2 && $course->major_id) {
                // Use the course's major if student's record major text matches, else attach course major
                $majorId = $course->major_id;
            }

            return [$yearInt, $yearLevelId, $majorId];
        }

        // Legacy fallback
        $yearInt = (int) ($student->academic_year ?? 1);
        $yearLevel = YearLevel::where('level', $yearInt)->first();

        return [$yearInt, $yearLevel?->id, null];
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\StudentYearRecord;

class CourseController extends Controller
{
    public function index()
    {
        $student = auth()->user();

        // Resolve the student's active academic record so we can scope the view
        $currentRecord = StudentYearRecord::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['yearLevel', 'academicYear'])
            ->latest()
            ->first();

        // Load enrollments joined to courses that match the student's current year/major.
        // The enrollment itself stores year_level_id + major_id — we filter on those so
        // only contextually correct courses appear even if stale enrollments exist.
        $query = Enrollment::with(['course.teacher', 'course.major', 'course.academicYear'])
            ->where('student_id', $student->id);

        if ($currentRecord) {
            $query->where(function ($q) use ($currentRecord) {
                // Match by year_level_id (new column) or fall back to integer `year`
                $q->where('year_level_id', $currentRecord->year_level_id)
                  ->orWhere(function ($q2) use ($currentRecord) {
                      $q2->whereNull('year_level_id')
                         ->where('year', $currentRecord->yearLevel?->level ?? 0);
                  });
            });
        }

        $courses = $query->latest()->paginate(15);

        // Mark course notifications as read when student visits My Courses
        \App\Models\UserNotification::markCategoryRead($student->id, 'course');

        return view('student.courses.index', compact('courses', 'currentRecord'));
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Exam;
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

        // Load enrollments with course.teacher included so the fallback path can
        // read courses.teacher_id when no exam snapshot exists yet for that year.
        // The primary source (exams.teacher_id) is resolved further below and
        // takes priority — course.teacher is only used as the fallback.
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

        $enrollments = $query->get()
            ->sortBy([
                fn ($e) => $e->course?->semester ?? 0,
                fn ($e) => $e->course?->title ?? '',
            ]);

        // ── Resolve teacher per course via exams.teacher_id with fallback ───────
        //
        // Priority 1 — exams.teacher_id (immutable snapshot):
        //   When an exam exists for the course + the student's enrollment academic year,
        //   use that exam's teacher_id. This is who actually taught the course that year.
        //
        // Priority 2 — courses.teacher_id (current assignment / fallback):
        //   When no exam has been created yet for this course in the enrollment year,
        //   fall back to courses.teacher_id. This shows the assigned teacher before
        //   any exam snapshot exists — e.g. a new academic year where exams are pending.
        //
        // CRITICAL: the exam lookup is scoped by BOTH course_id AND academic_year_id.
        // Without the academic year constraint a query by course_id alone would return
        // a 2022-2023 exam (T2) for a student enrolled in 2018-2019 (T1).
        $courseIds            = $enrollments->pluck('course_id')->filter()->unique()->values()->all();
        $enrollmentAcadYearId = $currentRecord?->academic_year_id;

        // Build exam snapshot map: course_id → best exam for this enrollment year
        $examsByCourseId = collect();
        if (! empty($courseIds) && $enrollmentAcadYearId) {
            $examsByCourseId = Exam::with('teacher')
                ->whereIn('course_id', $courseIds)
                ->where('academic_year_id', $enrollmentAcadYearId)
                ->whereNotNull('teacher_id')
                ->orderByRaw("FIELD(status, 'closed', 'published', 'approved', 'pending_approval', 'draft')")
                ->orderByDesc('id')
                ->get()
                ->groupBy('course_id')
                ->map(fn ($exams) => $exams->first());
        }

        // Attach historicalTeacher: exam snapshot if it exists, else course.teacher fallback.
        foreach ($enrollments as $enrollment) {
            $exam = $examsByCourseId[$enrollment->course_id] ?? null;

            $enrollment->historicalTeacher = $exam
                ? $exam->teacher                        // Priority 1: exam snapshot (immutable)
                : $enrollment->course?->teacher;        // Priority 2: current course assignment
        }

        $courses = $enrollments->groupBy(fn ($e) => $e->course?->semester ?? 0);

        // Mark course notifications as read when student visits My Courses
        \App\Models\UserNotification::markCategoryRead($student->id, 'course');

        return view('student.courses.index', compact('courses', 'currentRecord'));
    }
}

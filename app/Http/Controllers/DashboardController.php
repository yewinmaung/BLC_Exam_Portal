<?php

namespace App\Http\Controllers;

use App\Models\CheatingLog;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;

class DashboardController extends Controller
{
    public function admin()
    {
        $stats = [
            'users' => User::count(),
            'courses' => Course::count(),
            'exams' => Exam::count(),
            'cheating_logs' => CheatingLog::count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }

    public function teacher()
    {
        $teacherId = auth()->id();
        $stats = [
            'courses' => Course::where('teacher_id', $teacherId)->count(),
            'exams' => Exam::where('teacher_id', $teacherId)->count(),
            'pending_approval' => Exam::where('teacher_id', $teacherId)->where('status', 'pending_approval')->count(),
        ];

        return view('dashboard.teacher', compact('stats'));
    }

    public function student()
    {
        $studentId = auth()->id();
        $stats = [
            'enrolled_courses' => auth()->user()->enrollments()->count(),
            'completed_exams' => ExamAttempt::where('student_id', $studentId)->where('status', 'submitted')->count(),
        ];

        return view('dashboard.student', compact('stats'));
    }
}

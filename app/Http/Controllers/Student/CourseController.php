<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

class CourseController extends Controller
{
    public function index()
    {
        $courses = auth()->user()->enrollments()->with('course.teacher')->latest()->paginate(15);

        return view('student.courses.index', compact('courses'));
    }
}

<?php

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user()->load('role'));

    Route::get('/courses', fn () => Course::with('teacher')->paginate(20));

    Route::get('/exams', fn () => Exam::with(['course', 'activeSchedule'])->paginate(20));
});

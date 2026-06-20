<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\CheatingLogController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\ExamSessionController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Teacher\ExamController as TeacherExamController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');
Route::get('certificates/verify/{token}', [\App\Http\Controllers\Admin\CertificateController::class, 'verify'])->name('certificates.verify');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'exam.session'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{user}', [ChatController::class, 'conversation'])->name('chat.conversation');
    Route::post('chat/{user}', [ChatController::class, 'send'])->name('chat.send');
    Route::get('chat/{user}/poll', [ChatController::class, 'poll'])->name('chat.poll');

    Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/terminate', [UserController::class, 'terminate'])->name('users.terminate');
        Route::resource('courses', AdminCourseController::class)->except(['show']);
        Route::get('courses-by-year-level', [AdminCourseController::class, 'byYearLevel'])->name('courses.by-year-level');
        Route::get('enrollments', [\App\Http\Controllers\Admin\EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('enrollments', [\App\Http\Controllers\Admin\EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('enrollments/students-by-year-level', [\App\Http\Controllers\Admin\EnrollmentController::class, 'studentsByYearLevel'])->name('enrollments.students-by-year-level');
        Route::delete('enrollments/{enrollment}', [\App\Http\Controllers\Admin\EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::get('exams', [AdminExamController::class, 'index'])->name('exams.index');
        Route::get('exams/{exam}', [AdminExamController::class, 'show'])->name('exams.show');
        Route::post('exams/{exam}/approve', [AdminExamController::class, 'approve'])->name('exams.approve');
        Route::post('exams/{exam}/schedule', [AdminExamController::class, 'schedule'])->name('exams.schedule');
        Route::put('exams/{exam}/schedule/{schedule}', [AdminExamController::class, 'updateSchedule'])->name('exams.schedule.update');
        Route::delete('exams/{exam}/schedule/{schedule}', [AdminExamController::class, 'deleteSchedule'])->name('exams.schedule.delete');
        Route::post('exams/{exam}/publish', [AdminExamController::class, 'publish'])->name('exams.publish');
        Route::post('exams/{exam}/close', [AdminExamController::class, 'close'])->name('exams.close');
        Route::post('exams/{exam}/open', [AdminExamController::class, 'open'])->name('exams.open');
        Route::get('cheating-logs', [CheatingLogController::class, 'index'])->name('cheating-logs');

        // ── Email Management ──
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('/',                                                         [\App\Http\Controllers\Admin\EmailController::class, 'index'])->name('index');
            Route::get('smtp',                                                      [\App\Http\Controllers\Admin\EmailController::class, 'smtpSettings'])->name('smtp');
            Route::post('smtp',                                                     [\App\Http\Controllers\Admin\EmailController::class, 'smtpUpdate'])->name('smtp.update');
            Route::get('templates',                                                 [\App\Http\Controllers\Admin\EmailController::class, 'templates'])->name('templates');
            Route::get('templates/create',                                          [\App\Http\Controllers\Admin\EmailController::class, 'createTemplate'])->name('templates.create');
            Route::post('templates',                                                [\App\Http\Controllers\Admin\EmailController::class, 'storeTemplate'])->name('templates.store');
            Route::get('templates/{template}/edit',                                 [\App\Http\Controllers\Admin\EmailController::class, 'editTemplate'])->name('templates.edit');
            Route::put('templates/{template}',                                      [\App\Http\Controllers\Admin\EmailController::class, 'updateTemplate'])->name('templates.update');
            Route::delete('templates/{template}',                                   [\App\Http\Controllers\Admin\EmailController::class, 'destroyTemplate'])->name('templates.destroy');
            Route::get('templates/{template}/preview',                              [\App\Http\Controllers\Admin\EmailController::class, 'previewTemplate'])->name('templates.preview');
            Route::get('logs',                                                      [\App\Http\Controllers\Admin\EmailController::class, 'logs'])->name('logs');
            Route::get('logs/{log}',                                                [\App\Http\Controllers\Admin\EmailController::class, 'showLog'])->name('logs.show');
            Route::post('logs/{log}/retry',                                         [\App\Http\Controllers\Admin\EmailController::class, 'retryLog'])->name('logs.retry');
            Route::get('bulk',                                                      [\App\Http\Controllers\Admin\EmailController::class, 'bulk'])->name('bulk');
            Route::post('bulk',                                                     [\App\Http\Controllers\Admin\EmailController::class, 'sendBulk'])->name('bulk.send');
            Route::get('scheduled',                                                 [\App\Http\Controllers\Admin\EmailController::class, 'scheduled'])->name('scheduled');
            Route::post('scheduled',                                                [\App\Http\Controllers\Admin\EmailController::class, 'storeScheduled'])->name('scheduled.store');
            Route::delete('scheduled/{scheduled}',                                  [\App\Http\Controllers\Admin\EmailController::class, 'destroyScheduled'])->name('scheduled.destroy');
            Route::get('test',                                                      [\App\Http\Controllers\Admin\EmailController::class, 'testEmail'])->name('test');
            Route::post('test',                                                     [\App\Http\Controllers\Admin\EmailController::class, 'sendTestEmail'])->name('test.send');
        });

        // ── New Re-Attempt System ──
        Route::get('reattempts', [\App\Http\Controllers\Admin\ReAttemptController::class, 'index'])->name('reattempts.index');
        Route::get('reattempts/{reattempt}', [\App\Http\Controllers\Admin\ReAttemptController::class, 'show'])->name('reattempts.show');
        Route::post('reattempts/{reattempt}/approve', [\App\Http\Controllers\Admin\ReAttemptController::class, 'approve'])->name('reattempts.approve');
        Route::post('reattempts/{reattempt}/reject', [\App\Http\Controllers\Admin\ReAttemptController::class, 'reject'])->name('reattempts.reject');
        Route::put('reattempts/{reattempt}/window', [\App\Http\Controllers\Admin\ReAttemptController::class, 'updateWindow'])->name('reattempts.window.update');

        // ── Academic Year Management ──
        Route::resource('academic/years', \App\Http\Controllers\Admin\AcademicYearController::class)
            ->names('academic.years')
            ->parameters(['years' => 'year']);
        Route::get('academic/years/{year}/students', [\App\Http\Controllers\Admin\AcademicYearController::class, 'students'])->name('academic.years.students');
        Route::post('academic/years/{year}/students', [\App\Http\Controllers\Admin\AcademicYearController::class, 'assignStudents'])->name('academic.years.students.assign');
        Route::delete('academic/years/{year}/students/{student}', [\App\Http\Controllers\Admin\AcademicYearController::class, 'removeStudent'])->name('academic.years.students.remove');

        Route::get('teachers', [AdminTeacherController::class, 'index'])->name('teachers.index');
        Route::get('teachers/create', [AdminTeacherController::class, 'create'])->name('teachers.create');
        Route::post('teachers', [AdminTeacherController::class, 'store'])->name('teachers.store');
        Route::get('teachers/{teacher}', [AdminTeacherController::class, 'show'])->name('teachers.show');
        Route::get('teachers/{teacher}/edit', [AdminTeacherController::class, 'edit'])->name('teachers.edit');
        Route::put('teachers/{teacher}', [AdminTeacherController::class, 'update'])->name('teachers.update');

        Route::get('students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::get('students/create', [AdminStudentController::class, 'create'])->name('students.create');
        Route::post('students', [AdminStudentController::class, 'store'])->name('students.store');
        Route::get('students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
        Route::get('students/{student}/edit', [AdminStudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [AdminStudentController::class, 'update'])->name('students.update');
        Route::delete('students/{student}', [AdminStudentController::class, 'destroy'])->name('students.destroy');

        // ── Results ──
        Route::get('results', [\App\Http\Controllers\Admin\ResultController::class, 'index'])->name('results.index');
        Route::get('results/student/{student}', [\App\Http\Controllers\Admin\ResultController::class, 'student'])->name('results.student');

        // ── Transcripts & Certificates ──
        Route::prefix('academic')->name('academic.')->group(function () {
            Route::get('transcripts/{student}', [\App\Http\Controllers\Admin\TranscriptController::class, 'show'])->name('transcripts.show');
            Route::post('transcripts/{student}/generate', [\App\Http\Controllers\Admin\TranscriptController::class, 'generate'])->name('transcripts.generate');
            Route::get('transcripts/{student}/pdf', [\App\Http\Controllers\Admin\TranscriptController::class, 'pdf'])->name('transcripts.pdf');
            Route::get('certificates', [\App\Http\Controllers\Admin\CertificateController::class, 'index'])->name('certificates.index');
            Route::post('certificates/{student}/issue', [\App\Http\Controllers\Admin\CertificateController::class, 'issue'])->name('certificates.issue');
            Route::get('certificates/{cert}/pdf', [\App\Http\Controllers\Admin\CertificateController::class, 'pdf'])->name('certificates.pdf');
        });
    });

    Route::prefix('teacher')->middleware('role:teacher,admin')->name('teacher.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'teacher'])->name('dashboard');
        Route::get('profile', [TeacherProfileController::class, 'show'])->name('profile.show');
        Route::get('profile/edit', [TeacherProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [TeacherProfileController::class, 'update'])->name('profile.update');
        Route::get('exams', [TeacherExamController::class, 'index'])->name('exams.index');
        Route::get('exams/create', [TeacherExamController::class, 'create'])->name('exams.create');
        Route::post('exams', [TeacherExamController::class, 'store'])->name('exams.store');
        Route::get('exams/{exam}', [TeacherExamController::class, 'show'])->name('exams.show');
        Route::post('exams/{exam}/questions', [TeacherExamController::class, 'addQuestion'])->name('exams.questions.store');
        Route::get('exams/{exam}/questions/{question}/edit', [TeacherExamController::class, 'editQuestion'])->name('exams.questions.edit');
        Route::put('exams/{exam}/questions/{question}', [TeacherExamController::class, 'updateQuestion'])->name('exams.questions.update');
        Route::delete('exams/{exam}/questions/{question}', [TeacherExamController::class, 'deleteQuestion'])->name('exams.questions.destroy');
        Route::post('exams/{exam}/submit', [TeacherExamController::class, 'submitForApproval'])->name('exams.submit');
        Route::get('exams/{exam}/results', [TeacherExamController::class, 'results'])->name('exams.results');
        Route::post('exams/{exam}/import', [TeacherExamController::class, 'importQuestions'])->name('exams.import');

        // ── Result Reports ──
        Route::get('results', [\App\Http\Controllers\Teacher\ResultController::class, 'index'])->name('results.index');
        Route::get('reattempts', [TeacherExamController::class, 'reattemptRequests'])->name('reattempts.index');
        Route::get('reattempts/create', [TeacherExamController::class, 'reattemptCreate'])->name('reattempts.create');
        Route::post('reattempts', [TeacherExamController::class, 'reattemptStore'])->name('reattempts.store');
        Route::delete('reattempts/{reattempt}', [TeacherExamController::class, 'reattemptCancel'])->name('reattempts.cancel');
        Route::post('reattempts/{reattempt}/send-to-admin', [TeacherExamController::class, 'reattemptSendToAdmin'])->name('reattempts.send_to_admin');
    });

    Route::prefix('student')->middleware('role:student')->name('student.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'student'])->name('dashboard');
        Route::get('courses', [StudentCourseController::class, 'index'])->name('courses.index');
        Route::get('exams', [StudentExamController::class, 'index'])->name('exams.index');
        Route::get('exams/{exam}', [StudentExamController::class, 'show'])->name('exams.show');
        Route::post('exams/{exam}/start', [StudentExamController::class, 'start'])->name('exams.start');
        Route::get('attempt/{attempt}/take', [ExamSessionController::class, 'take'])->name('exam.take');
        Route::post('attempt/{attempt}/save', [ExamSessionController::class, 'saveAnswer'])->name('exam.save');
        Route::post('attempt/{attempt}/violation', [ExamSessionController::class, 'violation'])->name('exam.violation');
        Route::post('attempt/{attempt}/submit', [ExamSessionController::class, 'submit'])->name('exam.submit');
        Route::get('reattempts', [\App\Http\Controllers\Student\ReAttemptController::class, 'index'])->name('reattempts.index');
        Route::get('reattempts/create/{exam}', [\App\Http\Controllers\Student\ReAttemptController::class, 'create'])->name('reattempts.create');
        Route::post('reattempts', [\App\Http\Controllers\Student\ReAttemptController::class, 'store'])->name('reattempts.store');

        // ── My Results ──
        Route::get('results', [\App\Http\Controllers\Student\ResultController::class, 'index'])->name('results.index');
    });
});



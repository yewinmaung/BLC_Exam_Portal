<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailService;
use App\Services\EncryptionService;
use App\Services\ExamAccessService;
use App\Services\NotificationService;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExamController extends Controller
{
    public function __construct(
        private EncryptionService       $encryption,
        private ExamAccessService       $examAccess,
        private NotificationService     $notifications,
        private EmailService            $emailService,
        private QuestionImportService   $questionImport
    ) {
    }

    public function index()
    {
        $exams = Exam::with('course')
            ->where('teacher_id', auth()->id())
            ->latest()
            ->get();

        // Mark exam notifications as read when teacher opens Exams page
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'exam');

        return view('teacher.exams.index', compact('exams'));
    }

    public function create()
    {
        $courses = Course::where('teacher_id', auth()->id())->get();
        $categories = QuestionCategory::all();

        return view('teacher.exams.create', compact('courses', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id'          => 'required|exists:courses,id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'passing_marks'      => 'required|integer|min:0',
            'total_marks'        => 'required|integer|min:1',
            'shuffle_questions'  => 'nullable|boolean',
        ]);

        $exam = Exam::create([
            ...$data,
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
            'teacher_id'        => auth()->id(),
            'status'            => 'draft',
        ]);

        return redirect()->route('teacher.exams.show', $exam)->with('success', 'Exam created. Add questions.');
    }

    public function show(Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $exam->load(['questions.answers', 'course', 'latestSchedule']);
        $categories = QuestionCategory::all();
        $canDecrypt = $this->examAccess->canDecryptQuestions(auth()->user(), $exam)
            || $this->examAccess->canViewCorrectAnswers(auth()->user(), $exam);

        return view('teacher.exams.show', compact('exam', 'categories', 'canDecrypt'));
    }

    public function addQuestion(Request $request, Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $this->ensureEditable($exam);

        $data = $request->validate([
            'type'                    => 'required|in:mcq,true_false,essay,fill_blank',
            'content'                 => 'required|string',
            'marks'                   => 'required|integer|min:1',
            'difficulty'              => 'required|in:easy,medium,hard',
            'category_id'             => 'nullable|exists:question_categories,id',
            'answers'                 => 'nullable|array',
            'answers.*.content'       => 'nullable|string',
            'answers.*.is_correct'    => 'nullable',
            'blank_answers'           => 'nullable|array',
            'blank_answers.*'         => 'nullable|string',
        ]);

        if (empty(trim($data['content']))) {
            return back()->withErrors(['error' => 'Question text is required.']);
        }

        $question = Question::create([
            'exam_id'           => $exam->id,
            'type'              => $data['type'],
            'content_encrypted' => $this->encryption->encrypt($data['content']),
            'marks'             => $data['marks'],
            'difficulty'        => $data['difficulty'],
            'category_id'       => $data['category_id'] ?? null,
            'order'             => $exam->questions()->count() + 1,
        ]);

        if ($data['type'] === 'fill_blank') {
            $this->saveBlankAnswers($question, $data['blank_answers'] ?? []);
        } else {
            $this->saveAnswers($question, $data['type'], $data['answers'] ?? []);
        }

        // Notify admins that a question was added (only for pending_approval exams)
        if ($exam->status === 'pending_approval') {
            $this->notifyAdmins(
                'question_added',
                'Question Added to Pending Exam',
                auth()->user()->name . " added a new question to \"{$exam->title}\" which is pending your approval.",
                route('admin.exams.show', $exam)
            );
        }

        return back()->with('success', 'Question added.');
    }

    public function editQuestion(Exam $exam, Question $question)
    {
        $this->authorizeTeacher($exam);
        $this->ensureEditable($exam);

        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        $exam->load(['questions.answers', 'course']);
        $categories = QuestionCategory::all();
        $canDecrypt = true;

        return view('teacher.exams.edit-question', compact('exam', 'question', 'categories', 'canDecrypt'));
    }

    public function updateQuestion(Request $request, Exam $exam, Question $question)
    {
        $this->authorizeTeacher($exam);
        $this->ensureEditable($exam);

        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        $data = $request->validate([
            'type'                    => 'required|in:mcq,true_false,essay,fill_blank',
            'content'                 => 'required|string',
            'marks'                   => 'required|integer|min:1',
            'difficulty'              => 'required|in:easy,medium,hard',
            'category_id'             => 'nullable|exists:question_categories,id',
            'answers'                 => 'nullable|array',
            'answers.*.content'       => 'nullable|string',
            'answers.*.is_correct'    => 'nullable',
            'blank_answers'           => 'nullable|array',
            'blank_answers.*'         => 'nullable|string',
        ]);

        $question->update([
            'type'              => $data['type'],
            'content_encrypted' => $this->encryption->encrypt($data['content']),
            'marks'             => $data['marks'],
            'difficulty'        => $data['difficulty'],
            'category_id'       => $data['category_id'] ?? null,
        ]);

        // Rebuild answers
        $question->answers()->delete();

        if ($data['type'] === 'fill_blank') {
            $this->saveBlankAnswers($question, $data['blank_answers'] ?? []);
        } else {
            $this->saveAnswers($question, $data['type'], $data['answers'] ?? []);
        }

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'Question updated successfully.');
    }

    public function deleteQuestion(Exam $exam, Question $question)
    {
        $this->authorizeTeacher($exam);
        $this->ensureEditable($exam);

        if ($question->exam_id !== $exam->id) {
            abort(404);
        }

        if ($question->attachment_path) {
            Storage::disk('public')->delete($question->attachment_path);
        }

        $question->answers()->delete();
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    public function submitForApproval(Exam $exam)
    {
        $this->authorizeTeacher($exam);

        if (!in_array($exam->status, ['draft', 'pending_approval'])) {
            return back()->withErrors(['error' => 'This exam cannot be submitted.']);
        }

        if ($exam->questions()->count() === 0) {
            return back()->withErrors(['error' => 'Add at least one question before submitting.']);
        }

        // ── Backend marks validation (never trust the browser) ────────────────
        // Always recalculate from the database.
        $currentMarks = (int) $exam->questions()->sum('marks');
        $requiredMarks = (int) $exam->total_marks;

        if ($currentMarks < $requiredMarks) {
            $remaining = $requiredMarks - $currentMarks;
            return back()->withErrors(['error' =>
                "Cannot submit exam.\n" .
                "Required Total Marks: {$requiredMarks}\n" .
                "Current Question Marks: {$currentMarks}\n" .
                "Remaining Marks: {$remaining}\n" .
                "Please add more questions or adjust question marks before submitting."
            ]);
        }

        if ($currentMarks > $requiredMarks) {
            $excess = $currentMarks - $requiredMarks;
            return back()->withErrors(['error' =>
                "Cannot submit exam.\n" .
                "Required Total Marks: {$requiredMarks}\n" .
                "Current Question Marks: {$currentMarks}\n" .
                "You exceeded the required total by {$excess} mark(s).\n" .
                "Please remove questions or reduce mark values."
            ]);
        }
        // ── Marks match — proceed ─────────────────────────────────────────────

        \Illuminate\Support\Facades\DB::transaction(function () use ($exam) {
            $exam->update([
                'status'       => 'pending_approval',
                'submitted_at' => now(),
            ]);

            // Reload relationships needed for email/notification
            $exam->load(['teacher', 'course', 'questions']);

            // Fetch all admin users
            $adminRole = Role::where('slug', 'admin')->first();
            $admins    = $adminRole
                ? User::where('role_id', $adminRole->id)->where('is_active', true)->get()
                : collect();

            $teacher       = auth()->user();
            $questionCount = $exam->questions->count();
            $reviewLink    = route('admin.exams.show', $exam);

            foreach ($admins as $admin) {
                $this->notifications->notify(
                    $admin,
                    'exam_submitted',
                    'Exam Pending Approval',
                    "{$teacher->name} submitted \"{$exam->title}\" ({$questionCount} questions) for your review.",
                    $reviewLink
                );

                if ($admin->email) {
                    try {
                        $this->emailService->sendTemplate(
                            'exam_submitted',
                            $admin->email,
                            $admin->name,
                            [
                                'teacher_name' => $teacher->name,
                                'exam_name'    => $exam->title,
                                'course_name'  => $exam->course->title ?? '',
                            ],
                            'exam_submitted',
                            $admin->id,
                            true
                        );
                    } catch (\Throwable $e) {
                        logger()->error('ExamSubmittedMail failed: ' . $e->getMessage());
                    }
                }
            }
        });

        $exam->refresh();
        $adminCount = Role::where('slug', 'admin')->first()
            ? User::where('role_id', Role::where('slug', 'admin')->value('id'))->where('is_active', true)->count()
            : 0;

        return back()->with('success',
            "Exam submitted for approval. {$adminCount} admin(s) have been notified."
        );
    }

    public function results(Request $request, Exam $exam)
    {
        $this->authorizeTeacher($exam);

        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        // Base query
        $query = $exam->results()->with(['student', 'attempt.cheatingLogs']);

        // Apply search
        if ($search) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply filters
        switch ($filter) {
            case 'failed':
                $query->where('is_passed', false)
                      ->whereDoesntHave('attempt', function ($q) {
                          $q->whereIn('status', ['terminated', 'suspicious', 'terminated_pending_review']);
                      });
                break;

            case 'incomplete':
                // Students enrolled but no result
                $enrolledStudentIds  = $exam->course->enrollments()->pluck('student_id');
                $completedStudentIds = $exam->results()->pluck('student_id');
                $incompleteIds       = $enrolledStudentIds->diff($completedStudentIds);

                if ($incompleteIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $results = collect();
                    foreach ($incompleteIds as $studentId) {
                        $student = User::find($studentId);
                        if ($student) {
                            $results->push((object) [
                                'student'        => $student,
                                'obtained_marks' => 0,
                                'total_marks'    => $exam->total_marks,
                                'percentage'     => 0,
                                'is_passed'      => false,
                                'is_incomplete'  => true,
                                'attempt'        => null,
                            ]);
                        }
                    }
                    return view('teacher.exams.results', compact('exam', 'results', 'filter', 'search'));
                }
                break;

            case 'all':
            default:
                break;
        }

        $results = $query->latest()->get();

        return view('teacher.exams.results', compact('exam', 'results', 'filter', 'search'));
    }

    // ── Exam Analytics ─────────────────────────────────────────────────

    /**
     * Exam analytics summary — scoped to exams owned by this teacher.
     * Same definitions as Admin\ExamController::analytics().
     */
    public function analytics(Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $exam->load(['course', 'teacher', 'latestSchedule']);

        // ── Enrolled students ─────────────────────────────────────────────
        $enrolledIds   = $exam->course->enrollments()->pluck('student_id');
        $totalStudents = $enrolledIds->count();

        // ── Attempts — latest per student ─────────────────────────────────
        $attempts = \App\Models\ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('student_id', $enrolledIds)
            ->select('student_id', 'status', 'id')
            ->orderByDesc('attempt_number')
            ->get()
            ->unique('student_id');

        $attemptedIds = $attempts->pluck('student_id');

        $completed    = $attempts->where('status', 'submitted')->count();
        $inProgress   = $attempts->where('status', 'in_progress')->count();
        $terminated   = $attempts->whereIn('status', [
            'terminated', 'suspicious', 'terminated_pending_review', 'rejected',
        ])->count();
        $notAttempted = $enrolledIds->diff($attemptedIds)->count();

        // ── Pass / Fail ────────────────────────────────────────────────────
        $submittedAttemptIds = $attempts->where('status', 'submitted')->pluck('id');
        $passed = \App\Models\Result::where('exam_id', $exam->id)
            ->whereIn('attempt_id', $submittedAttemptIds)
            ->where('is_passed', true)
            ->count();
        $failed = \App\Models\Result::where('exam_id', $exam->id)
            ->whereIn('attempt_id', $submittedAttemptIds)
            ->where('is_passed', false)
            ->count();

        $stats = compact(
            'totalStudents', 'completed', 'inProgress',
            'terminated', 'notAttempted', 'passed', 'failed'
        );

        return view('teacher.exams.analytics', compact('exam', 'stats'));
    }

    /**
     * Detail view: students who have NOT attempted this exam yet (teacher-scoped).
     */
    public function analyticsNotAttempted(Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $exam->load(['course', 'teacher']);

        $enrolledIds  = $exam->course->enrollments()->pluck('student_id');
        $attemptedIds = \App\Models\ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('student_id', $enrolledIds)
            ->pluck('student_id')
            ->unique();

        $notAttemptedIds = $enrolledIds->diff($attemptedIds);

        $students = \App\Models\User::whereIn('id', $notAttemptedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('shared.exams.analytics-not-attempted', [
            'exam'              => $exam,
            'students'          => $students,
            'backRoute'         => route('teacher.exams.analytics', $exam),
            'sidebarView'       => 'partials.teacher-sidebar',
            'breadcrumbRole'    => 'Teacher',
            'breadcrumbRoleUrl' => route('teacher.dashboard'),
        ]);
    }

    // ── Question Import ────────────────────────────────────────────

    public function importQuestions(Request $request, Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $this->ensureEditable($exam);

        $request->validate([
            'import_file' => 'required|file|mimes:txt,pdf,doc,docx|max:5120',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $count = $this->questionImport->importFromFile(
            $exam,
            $request->file('import_file'),
            $request->input('category_id')
        );

        return back()->with('success', "{$count} question(s) imported successfully.");
    }


    private function notifyAdmins(string $type, string $title, string $message, string $link): void
    {
        $adminRole = \App\Models\Role::where('slug', 'admin')->first();
        if (!$adminRole) {
            return;
        }
        $admins = \App\Models\User::where('role_id', $adminRole->id)
            ->where('is_active', true)
            ->get();
        foreach ($admins as $admin) {
            $this->notifications->notify($admin, $type, $title, $message, $link);
        }
    }

    private function saveBlankAnswers(Question $question, array $blanks): void
    {
        $order = 0;
        foreach ($blanks as $blank) {
            $blank = trim($blank ?? '');
            if ($blank === '') {
                continue;
            }
            $order++;
            Answer::create([
                'question_id'       => $question->id,
                'content_encrypted' => $this->encryption->encrypt($blank),
                'is_correct'        => true,
                'is_blank_answer'   => true,
                'order'             => $order,
            ]);
        }
    }

    private function saveAnswers(Question $question, string $type, array $answers): void
    {
        if (!in_array($type, ['mcq', 'true_false'], true)) {
            return;
        }

        $order = 0;
        foreach ($answers as $answerData) {
            if (empty(trim($answerData['content'] ?? ''))) {
                continue;
            }
            $order++;
            Answer::create([
                'question_id' => $question->id,
                'content_encrypted' => $this->encryption->encrypt($answerData['content']),
                'is_correct' => !empty($answerData['is_correct']),
                'order' => $order,
            ]);
        }

        if ($type === 'true_false' && $question->answers()->count() === 0) {
            foreach (['True', 'False'] as $i => $label) {
                Answer::create([
                    'question_id' => $question->id,
                    'content_encrypted' => $this->encryption->encrypt($label),
                    'is_correct' => false,
                    'order' => $i + 1,
                ]);
            }
        }
    }

    private function ensureEditable(Exam $exam): void
    {
        if (!in_array($exam->status, ['draft', 'pending_approval'], true)) {
            abort(403, 'Cannot modify questions after approval.');
        }
    }

    private function authorizeTeacher(Exam $exam): void
    {
        if ($exam->teacher_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}

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
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_marks' => 'required|integer|min:0',
            'total_marks' => 'required|integer|min:1',
        ]);

        $exam = Exam::create([
            ...$data,
            'teacher_id' => auth()->id(),
            'status' => 'draft',
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

        if ($exam->questions()->count() === 0) {
            return back()->withErrors(['error' => 'Add at least one question before submitting.']);
        }

        if (!in_array($exam->status, ['draft', 'pending_approval'])) {
            return back()->withErrors(['error' => 'This exam cannot be submitted.']);
        }

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
            // In-app notification
            $this->notifications->notify(
                $admin,
                'exam_submitted',
                'Exam Pending Approval',
                "{$teacher->name} submitted \"{$exam->title}\" ({$questionCount} questions) for your review.",
                $reviewLink
            );

            // Email notification via EmailService (logged + queued)
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

        return back()->with('success',
            "Exam submitted for approval. {$admins->count()} admin(s) have been notified."
        );
    }

    public function results(Exam $exam)
    {
        $this->authorizeTeacher($exam);
        $results = $exam->results()->with('student', 'attempt')->latest()->get();

        return view('teacher.exams.results', compact('exam', 'results'));
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

    // ── Re-attempt request methods ─────────────────────────────

    public function reattemptRequests()
    {
        $requests = \App\Models\ReAttemptRequest::with(['student', 'exam.course', 'approver'])
            ->where('teacher_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('teacher.reattempts.index', compact('requests'));
    }

    public function reattemptSendToAdmin(\App\Models\ReAttemptRequest $reattempt)
    {
        if ($reattempt->teacher_id !== auth()->id()) {
            abort(403);
        }

        $service = app(\App\Services\ReAttemptService::class);
        $service->sendToAdmin($reattempt, auth()->user());

        return back()->with('success', 'Request sent to admin.');
    }

    public function reattemptCreate()
    {
        $exams = Exam::where('teacher_id', auth()->id())
            ->with('course')
            ->orderBy('title')
            ->get();
        $students = User::whereHas('role', fn($q) => $q->where('slug', 'student'))
            ->where('is_active', true)->orderBy('name')->get();

        return view('teacher.reattempts.create', compact('exams', 'students'));
    }

    public function reattemptStore(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'exam_id'    => 'required|exists:exams,id',
            'reason'     => 'required|string|max:1000',
        ]);

        // Ensure teacher owns the exam
        $exam = Exam::where('id', $data['exam_id'])
            ->where('teacher_id', auth()->id())
            ->firstOrFail();

        // Prevent duplicate pending requests
        $exists = \App\Models\ReAttemptRequest::where('student_id', $data['student_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'A pending re-attempt request already exists for this student and exam.']);
        }

        $student = User::findOrFail($data['student_id']);
        $service = app(\App\Services\ReAttemptService::class);
        $service->createRequest(auth()->user(), $student, $exam, $data['reason']);

        return redirect()->route('teacher.reattempts.index')
            ->with('success', "Re-attempt request submitted for {$student->name}.");
    }

    public function reattemptCancel(\App\Models\ReAttemptRequest $reattempt)
    {
        // Only the teacher who created it can cancel, and only if still pending
        if ($reattempt->teacher_id !== auth()->id()) {
            abort(403);
        }

        if (!$reattempt->isPending()) {
            return back()->withErrors(['error' => 'Only pending requests can be cancelled.']);
        }

        \App\Models\ReAttemptLog::create([
            'request_id' => $reattempt->id,
            'action'     => 'cancelled',
            'actor_id'   => auth()->id(),
            'actor_role' => 'teacher',
            'remarks'    => 'Cancelled by teacher',
        ]);

        $reattempt->delete();

        return back()->with('success', 'Re-attempt request cancelled.');
    }

    public function requestReset(Request $request, Exam $exam)
    {
        $this->authorizeTeacher($exam);

        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'reason'     => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate pending requests
        $exists = \App\Models\ReAttemptRequest::where('student_id', $data['student_id'])
            ->where('exam_id', $exam->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'A pending re-attempt request already exists for this student.']);
        }

        $student = User::findOrFail($data['student_id']);
        $service = app(\App\Services\ReAttemptService::class);
        $service->createRequest(auth()->user(), $student, $exam, $data['reason'] ?? 'Requested by teacher');

        return back()->with('success', 'Re-attempt request sent to admin for approval.');
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

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Role;
use App\Models\StudentYearRecord;
use App\Models\User;
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
        private QuestionImportService   $questionImport
    ) {
    }

    public function index()
    {
        $teacherId = auth()->id();

        // Load all teacher's exams with their own academic year + course info
        $exams = Exam::with(['academicYear', 'course'])
            ->withCount('questions')
            ->where('teacher_id', $teacherId)
            ->latest()
            ->get();

        // ── Build nested hierarchy: [ayId][yearLevel][semester][courseId] ─
        // Academic year comes from the exam's own academic_year_id, NOT the course.
        // Year level and semester still come from the course (structural metadata).
        $grouped = [];
        foreach ($exams as $exam) {
            $course = $exam->course;
            if (! $course) continue;

            $ayId = $exam->academic_year_id ?? 0;   // exam's own AY
            $yl   = (int) $course->year_level;      // from course
            $sem  = (int) $course->semester;        // from course

            $grouped[$ayId][$yl][$sem][$course->id]['course'] = $course;
            $grouped[$ayId][$yl][$sem][$course->id]['exams'][] = $exam;
        }

        // Academic year lookup (keyed by id) for the blade
        $ayIds = collect($exams)->pluck('academic_year_id')->filter()->unique();
        $ayMap = \App\Models\AcademicYear::whereIn('id', $ayIds)
            ->orderByDesc('start_year')
            ->get()
            ->keyBy('id');

        // Sort academic years descending (most recent first)
        krsort($grouped);

        // Mark exam notifications as read when teacher opens Exams page
        \App\Models\UserNotification::markCategoryRead($teacherId, 'exam');

        return view('teacher.exams.index', compact('grouped', 'ayMap', 'exams'));
    }

    public function create(Request $request)
    {
        $courses       = Course::where('teacher_id', auth()->id())->get();
        $categories    = QuestionCategory::all();
        $academicYears = \App\Models\AcademicYear::orderByDesc('start_year')->get();

        // Pre-selected course when coming from course detail page
        $selectedCourse = null;
        if ($request->filled('course_id')) {
            $selectedCourse = Course::where('id', $request->course_id)
                ->where('teacher_id', auth()->id())
                ->first();
        }

        return view('teacher.exams.create', compact('courses', 'categories', 'academicYears', 'selectedCourse'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id'          => 'required|exists:courses,id',
            'academic_year_id'   => 'required|exists:academic_years,id',
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

        // ── Correct answer validation ─────────────────────────────────────
        if ($data['type'] === 'fill_blank') {
            $validBlanks = array_filter(
                $data['blank_answers'] ?? [],
                fn($v) => trim($v ?? '') !== ''
            );
            if (empty($validBlanks)) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'Fill in the Blank questions require at least one accepted answer.']);
            }
        } else {
            // MCQ and True/False: at least one answer with is_correct = true
            $hasCorrect = false;
            foreach ($data['answers'] ?? [] as $a) {
                if (!empty($a['is_correct']) && trim($a['content'] ?? '') !== '') {
                    $hasCorrect = true;
                    break;
                }
            }
            if (!$hasCorrect) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'Please mark at least one answer as correct before saving the question.']);
            }
        }

        $question = Question::create([
            'exam_id'           => $exam->id,
            'type'              => $data['type'],
            'content_encrypted' => $this->encryption->encrypt($data['content']),
            'marks'             => $data['marks'],
           
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
           'category_id'             => 'nullable|exists:question_categories,id',
            'answers'                 => 'nullable|array',
            'answers.*.content'       => 'nullable|string',
            'answers.*.is_correct'    => 'nullable',
            'blank_answers'           => 'nullable|array',
            'blank_answers.*'         => 'nullable|string',
        ]);

        // ── Correct answer validation ─────────────────────────────────────
        if ($data['type'] === 'fill_blank') {
            $validBlanks = array_filter(
                $data['blank_answers'] ?? [],
                fn($v) => trim($v ?? '') !== ''
            );
            if (empty($validBlanks)) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'Fill in the Blank questions require at least one accepted answer.']);
            }
        } else {
            $hasCorrect = false;
            foreach ($data['answers'] ?? [] as $a) {
                if (!empty($a['is_correct']) && trim($a['content'] ?? '') !== '') {
                    $hasCorrect = true;
                    break;
                }
            }
            if (!$hasCorrect) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'Please mark at least one answer as correct before saving the question.']);
            }
        }

        $question->update([
            'type'              => $data['type'],
            'content_encrypted' => $this->encryption->encrypt($data['content']),
            'marks'             => $data['marks'],
           
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

    /**
     * Permanently delete a draft exam (teacher only, draft status only).
     * Uses forceDelete() on exam and questions (both use SoftDeletes) so records
     * are removed from the database entirely, not just soft-deleted.
     * Also removes any uploaded attachment files from storage.
     */
    public function destroy(Exam $exam)
    {
        $this->authorizeTeacher($exam);

        if ($exam->status !== 'draft') {
            return back()->withErrors(['error' => 'Only draft exams can be deleted.']);
        }

        $title = $exam->title;

        // Load questions including any already soft-deleted ones
        $questions = $exam->questions()->withTrashed()->get();

        foreach ($questions as $question) {
            // Delete attachment file from storage
            if ($question->attachment_path) {
                Storage::disk('public')->delete($question->attachment_path);
            }
            // Hard-delete all answers (Answer has no SoftDeletes)
            $question->answers()->delete();
            // Permanently remove the question row
            $question->forceDelete();
        }

        // Permanently remove the exam row
        $exam->forceDelete();

        return redirect()->route('teacher.exams.index')
            ->with('success', "Exam \"{$title}\" has been permanently deleted.");
    }

    public function submitForApproval(Exam $exam)    {
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

        $exam->load(['course', 'latestSchedule']);

        $search = $request->get('search', '');
        $filter = $request->get('filter', 'all');

        // ── All results for this exam ─────────────────────────────────────
        $query = $exam->results()->with(['student', 'attempt']);

        if ($search) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        switch ($filter) {
            case 'failed':
                // Failed = is_passed false regardless of reason (includes cheating/disqualified)
                $query->where('is_passed', false);
                break;

            case 'incomplete':
                // Return placeholder rows for enrolled-but-no-result students
                $enrolledIds   = $this->eligibleStudentIds($exam);
                $completedIds  = $exam->results()->pluck('student_id');
                $incompleteIds = $enrolledIds->diff($completedIds);

                $results = collect();
                foreach ($incompleteIds as $sid) {
                    $s = User::find($sid);
                    if ($s) {
                        $results->push((object) [
                            'student'        => $s,
                            'obtained_marks' => 0,
                            'total_marks'    => $exam->total_marks,
                            'percentage'     => 0,
                            'is_passed'      => false,
                            'is_incomplete'  => true,
                            'attempt'        => null,
                        ]);
                    }
                }

                // Stats (all results, not filtered)
                $allResults         = $exam->results()->with('student')->get();
                $enrolledStudentIds = $this->eligibleStudentIds($exam);
                $resultStudentIds   = $allResults->pluck('student_id');
                $absentStudents     = User::whereIn('id', $enrolledStudentIds)
                    ->whereNotIn('id', $resultStudentIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();

                $stats = $this->buildStats($exam, $allResults, $enrolledStudentIds);

                return view('teacher.exams.results', compact(
                    'exam', 'results', 'filter', 'search', 'stats', 'absentStudents'
                ));

            case 'all':
            default:
                break;
        }

        $results = $query->orderByDesc('percentage')->get();

        // ── Stats (always over all results, not the filtered subset) ─────
        $allResults         = $exam->results()->with('student')->get();
        $enrolledStudentIds = $this->eligibleStudentIds($exam);
        $resultStudentIds   = $allResults->pluck('student_id');

        $absentStudents = User::whereIn('id', $enrolledStudentIds)
            ->whereNotIn('id', $resultStudentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stats = $this->buildStats($exam, $allResults, $enrolledStudentIds);

        return view('teacher.exams.results', compact(
            'exam', 'results', 'filter', 'search', 'stats', 'absentStudents'
        ));
    }

    /**
     * Returns the IDs of students who are eligible for this exam:
     * - enrolled in the exam's course
     * - AND have a StudentYearRecord for the exam's academic year + course year level
     *
     * This prevents students from past academic years (same year level, different year)
     * from appearing in the candidate / absent-student list.
     */
    private function eligibleStudentIds(Exam $exam): \Illuminate\Support\Collection
    {
        $course = $exam->course;

        // All students enrolled in this course
        $enrolledIds = $course->enrollments()->pluck('student_id');

        if ($enrolledIds->isEmpty() || ! $exam->academic_year_id) {
            return $enrolledIds;
        }

        // Resolve the YearLevel ID that corresponds to the course's integer year_level
        $yearLevelId = \App\Models\YearLevel::where('level', $course->year_level)->value('id');

        if (! $yearLevelId) {
            return $enrolledIds;
        }

        // Keep only students whose record matches this exam's academic year + year level
        $eligibleIds = StudentYearRecord::whereIn('student_id', $enrolledIds)
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('year_level_id', $yearLevelId)
            ->pluck('student_id');

        return $eligibleIds;
    }

    /** Compute summary statistics for the results page. */
    private function buildStats(Exam $exam, $allResults, $enrolledStudentIds): array
    {
        return [
            'total_enrolled' => $enrolledStudentIds->count(),
            'total_taken'    => $allResults->count(),
            'passed'         => $allResults->where('is_passed', true)->count(),
            'failed'         => $allResults->where('is_passed', false)->count(),
            'avg_score'      => $allResults->count() > 0 ? round($allResults->avg('percentage'), 1) : 0,
            'highest_score'  => $allResults->max('percentage') ?? 0,
            'lowest_score'   => $allResults->min('percentage') ?? 0,
        ];
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

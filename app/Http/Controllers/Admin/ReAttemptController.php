<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ReAttemptRequest;
use App\Models\User;
use App\Services\ReAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReAttemptController extends Controller
{
    public function __construct(private ReAttemptService $service) {}

    public function index(Request $request)
    {
        $query = ReAttemptRequest::with(['student', 'teacher', 'exam.course', 'approver'])
            ->whereNotNull('sent_to_admin_at')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }
        if ($request->filled('course_id')) {
            $query->whereHas('exam', fn ($q) => $q->where('course_id', $request->course_id));
        }

        $requests  = $query->paginate(20)->withQueryString();
        $students  = User::whereHas('role', fn($q) => $q->where('slug','student'))->orderBy('name')->get();
        $teachers  = User::whereHas('role', fn($q) => $q->where('slug','teacher'))->orderBy('name')->get();
        $exams     = Exam::orderBy('title')->get();
        $courses   = \App\Models\Course::orderBy('title')->get();

        return view('admin.reattempts.index', compact('requests', 'students', 'teachers', 'exams', 'courses'));
    }

    public function show(ReAttemptRequest $reattempt)
    {
        $reattempt->load(['student','teacher','exam.course','approver','logs.actor']);
        return view('admin.reattempts.show', compact('reattempt'));
    }

    public function approve(Request $request, ReAttemptRequest $reattempt)
    {
        if (!$reattempt->isPending()) {
            return back()->withErrors(['error' => 'This request is already ' . $reattempt->status . '.']);
        }

        $data = $request->validate([
            'admin_remark' => 'nullable|string|max:500',
            're_attempt_start_at' => 'required|date',
            're_attempt_end_at' => 'required|date|after:re_attempt_start_at',
        ]);

        $this->service->approve(
            $reattempt,
            auth()->user(),
            $data['admin_remark'] ?? '',
            Carbon::parse($data['re_attempt_start_at'])->toDateTimeString(),
            Carbon::parse($data['re_attempt_end_at'])->toDateTimeString()
        );

        return back()->with('success', "Re-attempt approved for {$reattempt->student->name}.");
    }

    public function reject(Request $request, ReAttemptRequest $reattempt)
    {
        if (!$reattempt->isPending()) {
            return back()->withErrors(['error' => 'This request is already ' . $reattempt->status . '.']);
        }

        $data = $request->validate([
            'admin_remark' => 'required|string|max:500',
        ]);

        $this->service->reject($reattempt, auth()->user(), $data['admin_remark']);

        return back()->with('success', "Request rejected.");
    }

    public function updateWindow(Request $request, ReAttemptRequest $reattempt)
    {
        if (!$reattempt->isApproved()) {
            return back()->withErrors(['error' => 'Only approved requests can update window.']);
        }

        $data = $request->validate([
            're_attempt_start_at' => 'required|date',
            're_attempt_end_at' => 'required|date|after:re_attempt_start_at',
            'admin_remark' => 'nullable|string|max:500',
        ]);

        $reattempt->update([
            're_attempt_start_at' => Carbon::parse($data['re_attempt_start_at'])->toDateTimeString(),
            're_attempt_end_at' => Carbon::parse($data['re_attempt_end_at'])->toDateTimeString(),
            'admin_remark' => $data['admin_remark'] ?? $reattempt->admin_remark,
        ]);

        $reattempt->logs()->create([
            'action' => 'schedule_change',
            'actor_id' => auth()->id(),
            'actor_role' => 'admin',
            'remarks' => 'Re-attempt window updated by admin',
        ]);

        return back()->with('success', 'Re-attempt window updated.');
    }
}

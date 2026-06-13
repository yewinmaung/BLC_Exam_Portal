<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttemptResetRequest;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

class AttemptResetController extends Controller
{
    public function index()
    {
        $requests = AttemptResetRequest::with(['exam', 'student', 'requester'])
            ->latest()
            ->get();

        return view('admin.attempt-resets.index', compact('requests'));
    }

    public function approve(AttemptResetRequest $attemptReset)
    {
        if ($attemptReset->teacher_status !== 'approved') {
            return back()->withErrors(['error' => 'Teacher must approve first.']);
        }

        $attemptReset->update([
            'admin_status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        ExamAttempt::where('exam_id', $attemptReset->exam_id)
            ->where('student_id', $attemptReset->student_id)
            ->whereIn('status', ['terminated', 'suspicious', 'submitted'])
            ->delete();

        return back()->with('success', 'Re-attempt granted.');
    }

    public function reject(AttemptResetRequest $attemptReset)
    {
        $attemptReset->update(['admin_status' => 'rejected']);

        return back()->with('success', 'Request rejected.');
    }
}

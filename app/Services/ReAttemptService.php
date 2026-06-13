<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ReAttemptLog;
use App\Models\ReAttemptRequest;
use App\Models\User;
use Carbon\Carbon;

class ReAttemptService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Teacher creates a re-attempt request for a student.
     */
    public function createRequest(User $teacher, User $student, Exam $exam, string $reason): ReAttemptRequest
    {
        $request = ReAttemptRequest::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'exam_id'    => $exam->id,
            'reason'     => $reason,
            'status'     => 'pending',
            'sent_to_admin_at' => now(),
        ]);

        $this->log($request, 'create', $teacher, 'teacher', "Request sent from teacher {$teacher->name} to admin.");

        // Notify all admins
        $admins = User::whereHas('role', fn($q) => $q->where('slug','admin'))
            ->where('is_active', true)->get();
        foreach ($admins as $admin) {
            $this->notifications->notify(
                $admin, 're_attempt_submitted',
                'Re-Attempt Request',
                "{$teacher->name} submitted a re-attempt request for {$student->name} on \"{$exam->title}\".",
                route('admin.reattempts.show', $request)
            );
        }

        // Notify student
        $this->notifications->notify(
            $student, 're_attempt_submitted',
            'Re-Attempt Request Submitted',
            "Your teacher has submitted a re-attempt request for \"{$exam->title}\". Awaiting admin approval.",
            route('student.reattempts.index')
        );

        return $request;
    }

    /**
     * Student creates a re-attempt request to the teacher (not yet sent to admin).
     */
    public function createStudentRequest(User $student, Exam $exam, string $reason): ReAttemptRequest
    {
        $request = ReAttemptRequest::create([
            'teacher_id' => $exam->teacher_id,
            'student_id' => $student->id,
            'exam_id'    => $exam->id,
            'reason'     => $reason,
            'status'     => 'pending',
            'sent_to_admin_at' => null,
        ]);

        $this->log($request, 'create', $student, 'student', "Request sent from student {$student->name} to teacher.");

        // Notify the teacher only
        $this->notifications->notify(
            $exam->teacher,
            're_attempt_submitted',
            'Re-Attempt Request (Student)',
            "{$student->name} requested a re-attempt for \"{$exam->title}\". Review and send to admin.",
            route('teacher.reattempts.index')
        );

        return $request;
    }

    /**
     * Teacher forwards a student request to admin.
     */
    public function sendToAdmin(ReAttemptRequest $request, User $teacher): void
    {
        if ($request->teacher_id !== $teacher->id) {
            abort(403);
        }

        if ($request->sent_to_admin_at) {
            return;
        }

        $request->update(['sent_to_admin_at' => now()]);

        $this->log($request, 'submit_to_admin', $teacher, 'teacher', 'Teacher forwarded request to admin.');

        $admins = User::whereHas('role', fn($q) => $q->where('slug','admin'))
            ->where('is_active', true)->get();
        foreach ($admins as $admin) {
            $this->notifications->notify(
                $admin, 're_attempt_submitted',
                'Re-Attempt Request',
                "{$teacher->name} forwarded a re-attempt request for {$request->student->name} on \"{$request->exam->title}\".",
                route('admin.reattempts.show', $request)
            );
        }
    }

    /**
     * Admin approves the request.
     */
    public function approve(
        ReAttemptRequest $request,
        User $admin,
        string $remark = '',
        ?string $reAttemptStartAt = null,
        ?string $reAttemptEndAt = null
    ): void
    {
        $request->update([
            'status'       => 'approved',
            'admin_remark' => $remark,
            'approved_by'  => $admin->id,
            'approved_at'  => now(),
            're_attempt_start_at' => $reAttemptStartAt,
            're_attempt_end_at' => $reAttemptEndAt,
        ]);

        // IMPORTANT: do NOT delete previous attempts.
        // Business rule: admin approval adds exactly ONE extra attempt for this exam (max 3 overall),
        // and access is restricted to the approved re-attempt window.

        $logRemark = $remark ?: 'Approved by admin';
        if ($reAttemptStartAt && $reAttemptEndAt) {
            $logRemark .= " (Window: {$reAttemptStartAt} to {$reAttemptEndAt})";
        }

        $this->log($request, 'approved', $admin, 'admin', $logRemark);

        // Notify student
        $this->notifications->notify(
            $request->student, 're_attempt_approved',
            'Re-Attempt Approved ✓',
            "Your re-attempt request for \"{$request->exam->title}\" has been approved. You may now retake the exam."
            . ($reAttemptStartAt && $reAttemptEndAt ? " Window: {$reAttemptStartAt} to {$reAttemptEndAt}." : ''),
            route('student.exams.show', $request->exam_id)
        );

        // Notify teacher
        $this->notifications->notify(
            $request->teacher, 're_attempt_approved',
            'Re-Attempt Approved',
            "Your re-attempt request for {$request->student->name} on \"{$request->exam->title}\" was approved.",
            route('teacher.reattempts.index')
        );
    }

    /**
     * Admin rejects the request.
     */
    public function reject(ReAttemptRequest $request, User $admin, string $remark = ''): void
    {
        $request->update([
            'status'       => 'rejected',
            'admin_remark' => $remark,
            'approved_by'  => $admin->id,
            'approved_at'  => now(),
        ]);

        $this->log($request, 'rejected', $admin, 'admin', $remark ?: 'Rejected by admin');

        // Notify student
        $this->notifications->notify(
            $request->student, 're_attempt_rejected',
            'Re-Attempt Rejected',
            "Your re-attempt request for \"{$request->exam->title}\" was rejected." . ($remark ? " Reason: {$remark}" : ''),
            route('student.reattempts.index')
        );

        // Notify teacher
        $this->notifications->notify(
            $request->teacher, 're_attempt_rejected',
            'Re-Attempt Rejected',
            "Your re-attempt request for {$request->student->name} on \"{$request->exam->title}\" was rejected.",
            route('teacher.reattempts.index')
        );
    }

    /**
     * Check if a student has an approved re-attempt for an exam.
     */
    public function hasApproved(int $studentId, int $examId): bool
    {
        return ReAttemptRequest::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'approved')
            ->exists();
    }

    public function approvedCount(int $studentId, int $examId): int
    {
        return ReAttemptRequest::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'approved')
            ->count();
    }

    public function hasActiveApprovedWindow(int $studentId, int $examId): bool
    {
        $now = Carbon::now();

        return ReAttemptRequest::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->where('status', 'approved')
            ->whereNotNull('re_attempt_start_at')
            ->whereNotNull('re_attempt_end_at')
            ->where('re_attempt_start_at', '<=', $now)
            ->where('re_attempt_end_at', '>=', $now)
            ->exists();
    }

    private function log(ReAttemptRequest $request, string $action, User $actor, string $role, string $remarks = ''): void
    {
        ReAttemptLog::create([
            'request_id' => $request->id,
            'action'     => $action,
            'actor_id'   => $actor->id,
            'actor_role' => $role,
            'remarks'    => $remarks,
        ]);
    }
}

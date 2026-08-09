<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\User;
use App\Enums\RoleSlug;
use App\Models\StudentYearRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class EmailService
{
    // ── Public API ────────────────────────────────────────────────────

    /**
     * Send a welcome email to a newly created user (UserController path).
     *
     * Renders emails/welcome-account.blade.php directly — no DB template dependency.
     * If the user was created with a known password it can be passed; otherwise
     * an empty string is used and the template should handle it gracefully.
     */
    public function sendWelcomeEmail(User $user, string $temporaryPassword = ''): void
    {
        try {
            $roleLabel = match (true) {
                $user->isTeacher() => 'teacher',
                $user->isStudent() => 'student',
                default            => 'user',
            };

            $bodyHtml = view('emails.welcome-account', [
                'userName'          => $user->name,
                'userEmail'         => $user->email,
                'userRole'          => $roleLabel,
                'temporaryPassword' => $temporaryPassword,
            ])->render();

            $subject = '[' . config('app.name') . '] Welcome — Your Account is Ready';

            $this->send(
                toEmail:      $user->email,
                toName:       $user->name,
                subject:      $subject,
                bodyHtml:     $bodyHtml,
                event:        'welcome_account',
                templateSlug: null,
                userId:       $user->id,
                queue:        true,
                emailType:    'welcome'
            );
        } catch (\Throwable $e) {
            logger()->error("Welcome email failed for user #{$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Send an email immediately (or dispatch to queue).
     * Creates an EmailLog record and optionally dispatches SendEmailJob.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $event = null,
        string $templateSlug = null,
        int $userId = null,
        bool $queue = true,
        string $emailType = null
    ): EmailLog {
        $log = EmailLog::create([
            'to_email'      => $toEmail,
            'to_name'       => $toName,
            'from_email'    => config('mail.from.address', 'noreply@believeexam.com'),
            'from_name'     => config('mail.from.name', config('app.name')),
            'subject'       => $subject,
            'body_html'     => $bodyHtml,
            'template_slug' => $templateSlug,
            'event'         => $event,
            'email_type'    => $emailType,
            'status'        => 'queued',
            'provider'      => config('mail.default', 'smtp'),
            'user_id'       => $userId,
            'queued_at'     => now(),
        ]);

        if ($queue) {
            SendEmailJob::dispatch($log->id);
        } else {
            $this->deliver($log);
        }

        return $log;
    }

    /**
     * Bulk send to a recipient group.
     * Variables like {{student_name}}, {{course_name}} are substituted per recipient.
     */
    public function sendBulk(
        string $recipientGroup,
        string $subject,
        string $bodyHtml,
        string $event = 'bulk',
        string $templateSlug = null
    ): int {
        $users = $this->resolveRecipients($recipientGroup);
        $count = 0;

        foreach ($users as $user) {
            if (!$user->email) continue;

            // Build per-recipient variables and substitute them
            $vars            = $this->resolveUserVars($user);
            $personalSubject = $this->substituteVars($subject, $vars);
            $personalBody    = $this->substituteVars($bodyHtml, $vars);

            $this->send(
                $user->email, $user->name,
                $personalSubject, $personalBody,
                $event, $templateSlug,
                $user->id, true
            );
            $count++;
        }

        return $count;
    }

    /**
     * Actually deliver the email — called by SendEmailJob.
     */
    public function deliver(EmailLog $log): void
    {
        try {
            $from     = $log->from_email ?: config('mail.from.address', 'noreply@believeexam.com');
            $fromName = $log->from_name  ?: config('mail.from.name', config('app.name'));

            Mail::send([], [], function (Message $msg) use ($log, $from, $fromName) {
                $msg->to($log->to_email, $log->to_name ?? '')
                    ->from($from, $fromName)
                    ->subject($log->subject)
                    ->html($log->body_html ?? '');
            });

            $log->markSent();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            logger()->error("EmailService::deliver failed for log #{$log->id}: " . $e->getMessage());
        }
    }

    /**
     * Retry a failed email log.
     */
    public function retry(EmailLog $log): void
    {
        $log->update(['status' => 'queued', 'error' => null, 'queued_at' => now()]);
        SendEmailJob::dispatch($log->id);
    }

    // ── SMTP Settings ────────────────────────────────────────────────

    /**
     * Apply runtime SMTP settings from admin UI (does NOT persist to .env).
     * Changes take effect for the current request only.
     */
    public function applySmtpConfig(array $settings): void
    {
        config([
            'mail.mailers.smtp.host'       => $settings['host'],
            'mail.mailers.smtp.port'       => $settings['port'],
            'mail.mailers.smtp.username'   => $settings['username'],
            'mail.mailers.smtp.password'   => $settings['password'],
            'mail.mailers.smtp.encryption' => $settings['encryption'],
            'mail.from.address'            => $settings['from_address'],
            'mail.from.name'               => $settings['from_name'],
        ]);
    }

    // ── Variable Resolution ──────────────────────────────────────────

    /**
     * Build the variable map for a given User (student or teacher).
     * Covers all supported template variables.
     */
    public function resolveUserVars(User $user): array
    {
        $vars = [
            'student_name'  => $user->name,
            'teacher_name'  => $user->name,
            'name'          => $user->name,
            'email'         => $user->email,
            'student_id'    => 'STU-' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
            'app_name'      => config('app.name'),
            'app_url'       => config('app.url'),
            'year'          => now()->year,
        ];

        if ($user->isStudent()) {
            $record = StudentYearRecord::with(['yearLevel', 'academicYear'])
                ->where('student_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($record) {
                $vars['year_level']    = $record->yearLevel?->name ?? '';
                $vars['academic_year'] = $record->academicYear?->name ?? '';
                $vars['department']    = $record->department ?? '';
                $vars['major']         = $record->major ?? '';
                $vars['semester']      = 'Semester ' . ($record->semester ?? '');
            }

            $courseNames = $user->enrollments()
                ->with('course')
                ->get()
                ->pluck('course.title')
                ->filter()
                ->implode(', ');

            $vars['course_name'] = $courseNames ?: '';
            $vars['courses']     = $courseNames ?: '';
        }

        return $vars;
    }

    /**
     * Replace {{variable}} placeholders in a string with actual values.
     */
    public function substituteVars(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }
        return $text;
    }

    // ── Recipient Resolution ─────────────────────────────────────────

    /**
     * Resolve a named recipient group to a collection of active Users.
     */
    public function resolveRecipients(string $group): \Illuminate\Support\Collection
    {
        return match ($group) {
            'all_students' => User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))->where('is_active', true)->get(),
            'all_teachers' => User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::TEACHER))->where('is_active', true)->get(),
            'all_users'    => User::where('is_active', true)->get(),
            'first_year'   => $this->studentsByLevel(1),
            'second_year'  => $this->studentsByLevel(2),
            'third_year'   => $this->studentsByLevel(3),
            'fourth_year'  => $this->studentsByLevel(4),
            'final_year'   => $this->studentsByLevel(5),
            default        => collect(),
        };
    }

    /**
     * Recipient group labels — used by Compose and Bulk send panels.
     */
    public static function recipientLabels(): array
    {
        return [
            'all_students' => 'All Students',
            'first_year'   => 'First Year Students',
            'second_year'  => 'Second Year Students',
            'third_year'   => 'Third Year Students',
            'fourth_year'  => 'Fourth Year Students',
            'final_year'   => 'Final Year Students',
            'all_teachers' => 'All Teachers',
            'all_users'    => 'All Users',
        ];
    }

    private function studentsByLevel(int $level): \Illuminate\Support\Collection
    {
        $ids = StudentYearRecord::whereHas('yearLevel', fn ($q) => $q->where('level', $level))
            ->pluck('student_id')
            ->unique();

        return User::whereIn('id', $ids)->where('is_active', true)->get();
    }

    /**
     * Resolve students matching the given academic filter arrays.
     * Used by the Exam Timetable Notification feature.
     *
     * An empty filter array means "no restriction on that dimension".
     * A student must match ALL provided filters (AND logic across dimensions).
     */
    public function resolveAcademicRecipients(
        array $academicYearIds,
        array $yearLevelIds,
        array $majorIds
    ): \Illuminate\Support\Collection {
        $query = StudentYearRecord::query()
            ->where('status', 'active')
            ->with('student');

        if (!empty($academicYearIds)) {
            $query->whereIn('academic_year_id', $academicYearIds);
        }

        if (!empty($yearLevelIds)) {
            $query->whereIn('year_level_id', $yearLevelIds);
        }

        if (!empty($majorIds)) {
            $majorNames = \App\Models\Major::whereIn('id', $majorIds)->pluck('name')->toArray();
            if (!empty($majorNames)) {
                $query->whereIn('major', $majorNames);
            }
        }

        return $query->get()
            ->pluck('student')
            ->filter(fn ($u) => $u && $u->is_active && $u->email)
            ->unique('id')
            ->values();
    }
}

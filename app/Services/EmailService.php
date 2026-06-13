<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ScheduledEmail;
use App\Models\User;
use App\Enums\RoleSlug;
use App\Models\StudentYearRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class EmailService
{
    // ── Public API ────────────────────────────────────────────────────

    /**
     * Send an email immediately (or dispatch to queue).
     * Uses an EmailTemplate slug if provided; otherwise uses raw subject/html.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $event = null,
        string $templateSlug = null,
        int $userId = null,
        bool $queue = true
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
     * Send via template slug with variable substitution.
     */
    public function sendTemplate(
        string $templateSlug,
        string $toEmail,
        string $toName,
        array $vars = [],
        string $event = null,
        int $userId = null,
        bool $queue = true
    ): ?EmailLog {
        $template = EmailTemplate::findBySlug($templateSlug);

        if (!$template) {
            logger()->warning("EmailService: template '{$templateSlug}' not found or inactive.");
            return null;
        }

        $rendered = $template->render($vars);

        return $this->send(
            $toEmail, $toName,
            $rendered['subject'], $rendered['bodyHtml'],
            $event ?? $templateSlug, $templateSlug,
            $userId, $queue
        );
    }

    /**
     * Bulk send to a recipient group.
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
            $this->send(
                $user->email, $user->name,
                $subject, $bodyHtml,
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
            $from    = $log->from_email ?: config('mail.from.address', 'noreply@believeexam.com');
            $fromName = $log->from_name ?: config('mail.from.name', config('app.name'));

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

    /**
     * Process all due scheduled emails.
     */
    public function processScheduled(): int
    {
        $due = ScheduledEmail::where('is_sent', false)
            ->where('send_at', '<=', now())
            ->get();

        $total = 0;

        foreach ($due as $scheduled) {
            $count = $this->sendBulk(
                $scheduled->recipients,
                $scheduled->subject,
                $scheduled->body_html,
                'scheduled',
                $scheduled->template_slug
            );

            $scheduled->update(['is_sent' => true, 'sent_at' => now()]);
            $total += $count;
        }

        return $total;
    }

    // ── SMTP Settings ────────────────────────────────────────────────

    /**
     * Apply runtime SMTP settings from admin UI (does NOT persist to .env).
     * Changes take effect for the current request only; restart required for permanent change.
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

    // ── Recipient Resolution ─────────────────────────────────────────

    public function resolveRecipients(string $group): \Illuminate\Support\Collection
    {
        return match ($group) {
            'all_students'  => User::whereHas('role', fn($q) => $q->where('slug', RoleSlug::STUDENT))->where('is_active', true)->get(),
            'all_teachers'  => User::whereHas('role', fn($q) => $q->where('slug', RoleSlug::TEACHER))->where('is_active', true)->get(),
            'all_users'     => User::where('is_active', true)->get(),
            'first_year'    => $this->studentsByLevel(1),
            'second_year'   => $this->studentsByLevel(2),
            'third_year'    => $this->studentsByLevel(3),
            'fourth_year'   => $this->studentsByLevel(4),
            'final_year'    => $this->studentsByLevel(5),
            default         => collect(),
        };
    }

    private function studentsByLevel(int $level): \Illuminate\Support\Collection
    {
        $ids = StudentYearRecord::whereHas('yearLevel', fn($q) => $q->where('level', $level))
            ->pluck('student_id')
            ->unique();

        return User::whereIn('id', $ids)->where('is_active', true)->get();
    }
}

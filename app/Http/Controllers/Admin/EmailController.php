<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Exam;
use App\Models\Major;
use App\Models\ScheduledEmail;
use App\Models\YearLevel;
use App\Services\ActivityLogService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class EmailController extends Controller
{
    public function __construct(
        private EmailService      $emailService,
        private ActivityLogService $activityLog
    ) {}

    // ── Dashboard ─────────────────────────────────────────────────────

    public function index()
    {
        $stats = [
            'total'   => EmailLog::count(),
            'sent'    => EmailLog::where('status', 'sent')->count(),
            'queued'  => EmailLog::where('status', 'queued')->count(),
            'failed'  => EmailLog::where('status', 'failed')->count(),
        ];

        $recentLogs = EmailLog::latest()->limit(10)->get();
        $templates  = EmailTemplate::orderBy('name')->get();

        return view('admin.email.index', compact('stats', 'recentLogs', 'templates'));
    }

    // ── SMTP Settings ────────────────────────────────────────────────

    public function smtpSettings()
    {
        return view('admin.email.smtp', [
            'settings' => [
                'host'         => config('mail.mailers.smtp.host'),
                'port'         => config('mail.mailers.smtp.port'),
                'username'     => config('mail.mailers.smtp.username'),
                'password'     => '', // never echo password
                'encryption'   => config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name'    => config('mail.from.name'),
            ],
        ]);
    }

    public function smtpUpdate(Request $request)
    {
        $data = $request->validate([
            'host'         => 'required|string|max:255',
            'port'         => 'required|integer|min:1|max:65535',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:255',
            'encryption'   => 'required|in:tls,ssl,none',
            'from_address' => 'required|email|max:255',
            'from_name'    => 'required|string|max:255',
        ]);

        $this->writeEnvValues([
            'MAIL_HOST'         => $data['host'],
            'MAIL_PORT'         => $data['port'],
            'MAIL_USERNAME'     => $data['username'] ?? '',
            'MAIL_PASSWORD'     => $data['password'] ?: config('mail.mailers.smtp.password', ''),
            'MAIL_ENCRYPTION'   => $data['encryption'] === 'none' ? 'null' : $data['encryption'],
            'MAIL_FROM_ADDRESS' => '"' . $data['from_address'] . '"',
            'MAIL_FROM_NAME'    => '"' . $data['from_name'] . '"',
        ]);

        Artisan::call('config:clear');

        $this->activityLog->log('smtp_updated', 'Admin updated SMTP settings');

        return back()->with('success', 'SMTP settings saved. Config cache cleared.');
    }

    // ── Email Templates ──────────────────────────────────────────────

    public function templates()
    {
        $templates = EmailTemplate::latest()->paginate(20);
        return view('admin.email.templates.index', compact('templates'));
    }

    public function createTemplate()
    {
        $template = new EmailTemplate();   // empty model so _form ?? fallbacks work
        return view('admin.email.templates.create', compact('template'));
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'required|string|max:100|unique:email_templates,slug|regex:/^[a-z0-9_]+$/',
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'event'     => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        EmailTemplate::create([...$data, 'created_by' => auth()->id()]);
        $this->activityLog->log('email_template_created', "Created email template: {$data['name']}");

        return redirect()->route('admin.email.templates')
            ->with('success', 'Template created.');
    }

    public function editTemplate(EmailTemplate $template)
    {
        return view('admin.email.templates.edit', compact('template'));
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'required|string|max:100|regex:/^[a-z0-9_]+$/|unique:email_templates,slug,' . $template->id,
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'event'     => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $template->update($data);
        $this->activityLog->log('email_template_updated', "Updated email template: {$template->name}");

        return redirect()->route('admin.email.templates')
            ->with('success', 'Template updated.');
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $name = $template->name;
        $template->delete();
        $this->activityLog->log('email_template_deleted', "Deleted email template: {$name}");

        return back()->with('success', 'Template deleted.');
    }

    public function previewTemplate(EmailTemplate $template)
    {
        $sampleVars = [
            'student_name'  => 'John Doe',
            'student_id'    => 'STU-2026-001',
            'teacher_name'  => 'Prof. Smith',
            'course_name'   => 'Computer Science 101',
            'exam_name'     => 'Midterm Exam',
            'result'        => 'Passed',
            'gpa'           => '3.75',
        ];

        $rendered = $template->render($sampleVars);

        return view('admin.email.templates.preview', compact('template', 'rendered'));
    }

    // ── Email Logs ───────────────────────────────────────────────────

    public function logs(Request $request)
    {
        $query = EmailLog::latest();

        if ($request->filled('status'))   { $query->where('status', $request->status); }
        if ($request->filled('email'))    { $query->where('to_email', 'like', '%'.$request->email.'%'); }
        if ($request->filled('event'))    { $query->where('event', $request->event); }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.email.logs', compact('logs'));
    }

    public function showLog(EmailLog $log)
    {
        return view('admin.email.log-show', compact('log'));
    }

    public function retryLog(EmailLog $log)
    {
        if ($log->status !== 'failed') {
            return back()->withErrors(['error' => 'Only failed emails can be retried.']);
        }

        $this->emailService->retry($log);
        $this->activityLog->log('email_retried', "Retried email log #{$log->id}");

        return back()->with('success', 'Email queued for retry.');
    }

    // ── Inbox ────────────────────────────────────────────────────────

    /**
     * Inbox list — shows one row per thread root, newest activity first.
     * Threads with multiple messages show a message count badge.
     */
    public function inbox(Request $request)
    {
        // Show one representative row per thread: the most-recent message
        // in each thread (or standalone messages with no thread).
        $query = \App\Models\InboxEmail::query()
            ->select('inbox_emails.*')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($q2) use ($s) {
                    $q2->where('from_email', 'like', '%'.$s.'%')
                       ->orWhere('from_name',  'like', '%'.$s.'%')
                       ->orWhere('subject',    'like', '%'.$s.'%');
                });
            })
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->input('status'))
            )
            // Show only thread roots (parent_id IS NULL) or standalone messages
            // so each conversation appears once. Threads are expanded in the show view.
            ->whereNull('parent_id')
            ->orderByDesc('received_at');

        $emails      = $query->paginate(25)->withQueryString();
        $unreadCount = \App\Models\InboxEmail::where('status', 'unread')->count();

        // Attach thread message counts for badge display
        $threadIds = $emails->pluck('thread_id')->filter()->unique()->values()->toArray();
        $threadCounts = [];
        if (!empty($threadIds)) {
            $threadCounts = \App\Models\InboxEmail::whereIn('thread_id', $threadIds)
                ->selectRaw('thread_id, COUNT(*) as cnt')
                ->groupBy('thread_id')
                ->pluck('cnt', 'thread_id')
                ->toArray();
        }

        return view('admin.email.inbox', compact('emails', 'unreadCount', 'threadCounts'));
    }

    /**
     * Show a single inbox email with its full thread conversation.
     * Marks the opened message (and any unread messages in the thread) as read.
     */
    public function showInbox(\App\Models\InboxEmail $inboxEmail)
    {
        // Mark this message as read
        if ($inboxEmail->status === 'unread') {
            $inboxEmail->update(['status' => 'read']);
        }

        // Load entire thread for conversation view
        $thread = collect([$inboxEmail]);
        if ($inboxEmail->thread_id) {
            $thread = \App\Models\InboxEmail::where('thread_id', $inboxEmail->thread_id)
                ->with('replier')
                ->orderBy('received_at')
                ->get();

            // Mark all unread thread messages as read
            \App\Models\InboxEmail::where('thread_id', $inboxEmail->thread_id)
                ->where('status', 'unread')
                ->update(['status' => 'read']);
        }

        return view('admin.email.inbox.show', compact('inboxEmail', 'thread'));
    }

    /**
     * Mark a single inbox email as read (AJAX-friendly POST).
     */
    public function markInboxRead(\App\Models\InboxEmail $inboxEmail)
    {
        $inboxEmail->update(['status' => 'read']);
        return back()->with('success', 'Marked as read.');
    }

    /**
     * Reply to an inbox email with proper RFC 2822 threading headers.
     *
     * Builds In-Reply-To and References headers so the reply is correctly
     * threaded in the recipient's email client.  Uses the existing
     * EmailService::send() + SendEmailJob flow — nothing in that pipeline
     * is changed.
     */
    public function replyInbox(Request $request, \App\Models\InboxEmail $inboxEmail)
    {
        $request->validate([
            'reply_body' => ['required', 'string', 'min:5'],
            'subject'    => ['nullable', 'string', 'max:255'],
        ]);

        // Build RFC 2822 compliant subject
        $baseSubject = $inboxEmail->subject;
        $reSubject   = str_starts_with(strtolower($baseSubject), 're:')
            ? $baseSubject
            : 'Re: ' . $baseSubject;
        $subject = $request->input('subject') ?: $reSubject;

        // Build body: reply text + quoted original
        $quotedBody = $this->buildQuotedReply(
            $request->input('reply_body'),
            $inboxEmail
        );

        // Store reply as a new InboxEmail to maintain the thread locally
        // (admin outgoing replies tracked in the same thread)
        $replyMessageId = '<reply-' . uniqid() . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>';

        // Build References header: parent's References + parent's Message-ID
        $parentRefs    = $inboxEmail->references ?? '';
        $parentMsgId   = $inboxEmail->message_id ? '<' . $inboxEmail->message_id . '>' : null;
        $newReferences = trim($parentRefs . ($parentMsgId ? ' ' . $parentMsgId : ''));

        // Resolve thread_id for the reply
        $threadId = $inboxEmail->thread_id ?? md5($inboxEmail->message_id ?? $inboxEmail->id);

        // Record the outgoing reply in inbox_emails as a thread member
        $replyRecord = \App\Models\InboxEmail::create([
            'from_email'  => config('mail.from.address'),
            'from_name'   => config('mail.from.name'),
            'sender_type' => 'external',
            'user_id'     => auth()->id(),
            'subject'     => $subject,
            'body_html'   => $quotedBody,
            'body_text'   => $request->input('reply_body'),
            'message_id'  => trim($replyMessageId, '<>'),
            'in_reply_to' => $inboxEmail->message_id,
            'references'  => $newReferences ?: null,
            'thread_id'   => $threadId,
            'parent_id'   => $inboxEmail->id,
            'status'      => 'replied',
            'replied_by'  => auth()->id(),
            'replied_at'  => now(),
            'received_at' => now(),
        ]);

        // Send via existing EmailService (SMTP flow unchanged)
        $this->emailService->send(
            $inboxEmail->from_email,
            $inboxEmail->from_name ?: '',
            $subject,
            $quotedBody,
            'inbox_reply',
            null,
            auth()->id(),
            true,
            'inbox_reply'
        );

        // Mark original as replied
        $inboxEmail->update([
            'status'     => 'replied',
            'replied_by' => auth()->id(),
            'replied_at' => now(),
        ]);

        $this->activityLog->log(
            'inbox_reply_sent',
            "Replied to inbox email #{$inboxEmail->id} from {$inboxEmail->from_email}"
        );

        return redirect()->route('admin.email.inbox.show', $inboxEmail)
            ->with('success', "Reply queued for delivery to {$inboxEmail->from_email}.");
    }

    /**
     * AJAX endpoint for real-time inbox polling.
     * Returns new inbox messages received after a given timestamp.
     * Used as a fallback when WebSocket/Pusher is not configured.
     *
     * GET /admin/email/inbox/poll?since=<ISO8601>
     */
    public function pollInbox(Request $request)
    {
        $since = $request->input('since');

        $query = \App\Models\InboxEmail::whereNull('parent_id')
            ->orderByDesc('received_at')
            ->limit(10);

        if ($since) {
            try {
                $sinceDate = \Carbon\Carbon::parse($since);
                $query->where('received_at', '>', $sinceDate);
            } catch (\Throwable $e) {
                // Invalid date — return empty
                return response()->json(['emails' => [], 'unread_count' => 0]);
            }
        }

        $newEmails = $query->get()->map(fn ($e) => [
            'id'           => $e->id,
            'from_email'   => $e->from_email,
            'display_name' => $e->display_name,
            'subject'      => $e->subject,
            'sender_type'  => $e->sender_type,
            'thread_id'    => $e->thread_id,
            'status'       => $e->status,
            'received_at'  => $e->received_at->toIso8601String(),
            'received_fmt' => $e->received_at->format('d M Y H:i'),
            'show_url'     => route('admin.email.inbox.show', $e->id),
        ]);

        return response()->json([
            'emails'       => $newEmails,
            'unread_count' => \App\Models\InboxEmail::where('status', 'unread')->count(),
        ]);
    }

    /**
     * Archive an inbox email (soft-archive via status change, no DB delete).
     */
    public function archiveInbox(\App\Models\InboxEmail $inboxEmail)
    {
        $inboxEmail->update(['status' => 'archived']);
        $this->activityLog->log('inbox_archived', "Archived inbox email #{$inboxEmail->id}");
        return redirect()->route('admin.email.inbox')
            ->with('success', 'Email archived.');
    }

    /**
     * Build the quoted reply body HTML.
     * Wraps the admin's reply above a collapsible quote of the original message.
     */
    private function buildQuotedReply(string $replyText, \App\Models\InboxEmail $original): string
    {
        $replyHtml = nl2br(e($replyText));
        $dateStr   = $original->received_at->format('D, d M Y \a\t H:i');
        $from      = e($original->display_name) . ' &lt;' . e($original->from_email) . '&gt;';
        $origBody  = $original->body_html
            ? '<div style="margin-left:12px;padding-left:12px;border-left:3px solid #d1d5db;color:#6b7280;font-size:0.85em">'
              . $original->body_html
              . '</div>'
            : '<div style="margin-left:12px;padding-left:12px;border-left:3px solid #d1d5db;color:#6b7280;font-size:0.85em">'
              . nl2br(e($original->body_text ?? ''))
              . '</div>';

        return <<<HTML
<div>{$replyHtml}</div>
<br>
<div style="font-size:0.82rem;color:#6b7280;margin-top:16px">
    On {$dateStr}, {$from} wrote:
</div>
{$origBody}
HTML;
    }

    /**
     * Sync inbox from Gmail via IMAP.
     *
     * Runs InboxSyncService::sync() directly (not queued) so the result is
     * immediately visible. set_time_limit(0) prevents PHP-FPM from killing
     * the request during the IMAP session — this is safe for an admin-only
     * action. The InboxSyncService already limits the fetch to IMAP_SYNC_LIMIT
     * (default 20) messages and skips body download until dedup passes, so
     * the actual wall-clock time is bounded.
     */
    public function syncInbox(Request $request)
    {
        // Remove the 60 s wall-clock limit for this admin request only.
        // The InboxSyncService fetch limit (default 20 msgs) keeps this fast.
        @set_time_limit(0);

        $syncService = app(\App\Services\InboxSyncService::class);

        try {
            $result = $syncService->sync();

            $this->activityLog->log(
                'inbox_synced',
                "Inbox sync: {$result['imported']} imported, {$result['skipped']} skipped, {$result['errors']} errors."
            );

            if ($result['errors'] > 0 && $result['imported'] === 0) {
                return redirect()->route('admin.email.inbox')
                    ->withErrors(['error' => 'Sync failed: ' . $result['message']]);
            }

            $msg = $result['imported'] > 0
                ? "{$result['imported']} new email(s) imported. {$result['skipped']} already existed."
                : "Sync complete — no new emails. ({$result['skipped']} already existed)";

            return redirect()->route('admin.email.inbox')
                ->with('success', $msg);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('EmailController::syncInbox — ' . $e->getMessage());
            return redirect()->route('admin.email.inbox')
                ->withErrors(['error' => 'Sync error: ' . $e->getMessage()]);
        }
    }

    // ── Compose ──────────────────────────────────────────────────────

    /**
     * The list of variables that EmailService::resolveUserVars() and the system
     * provide automatically — these do NOT need a manual input field.
     */
    private const AUTO_VARS = [
        'student_name', 'teacher_name', 'name', 'email', 'student_id',
        'app_name', 'app_url', 'year',
        'year_level', 'academic_year', 'department', 'major', 'semester',
        'course_name', 'courses',
    ];

    /**
     * Scan a template's subject + body_html for {{variable}} tokens.
     * Returns an array of unique variable key names found.
     */
    private function extractTemplateVariables(EmailTemplate $template): array
    {
        $haystack = $template->subject . ' ' . $template->body_html;
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $haystack, $matches);
        return array_values(array_unique($matches[1]));
    }

    public function compose()
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get()
            ->map(function (EmailTemplate $t) {
                $allVars    = $this->extractTemplateVariables($t);
                $manualVars = array_values(array_diff($allVars, self::AUTO_VARS));
                $autoVars   = array_values(array_intersect($allVars, self::AUTO_VARS));

                // Attach as transient properties — not persisted
                $t->all_vars    = $allVars;
                $t->manual_vars = $manualVars; // admin must fill these
                $t->auto_vars   = $autoVars;   // resolved automatically

                return $t;
            });

        $groups = ScheduledEmail::$recipientLabels;

        return view('admin.email.compose', compact('templates', 'groups'));
    }

    /**
     * AJAX endpoint — renders a template with provided vars and returns JSON.
     * Used by the preview panel. Does NOT send email or create any log.
     *
     * POST /admin/email/compose/preview
     * Body: template_slug, vars{key:value,...}, mode, to_email (optional)
     * Returns: JSON { subject, body_html, recipient_info }
     */
    public function composePreview(Request $request)
    {
        $request->validate([
            'template_slug' => ['required', 'string', 'exists:email_templates,slug'],
            'vars'          => ['nullable', 'array'],
            'vars.*'        => ['nullable', 'string', 'max:500'],
            'mode'          => ['required', 'in:single,group'],
            'to_email'      => ['nullable', 'email'],
            'recipients'    => ['nullable', 'string'],
        ]);

        $template = EmailTemplate::where('slug', $request->input('template_slug'))->firstOrFail();
        $adminVars = $request->input('vars', []);

        // Build variable map: system vars + user vars (sample) + admin-provided vars
        $systemVars = [
            'app_name' => config('app.name'),
            'app_url'  => config('app.url'),
            'year'     => now()->year,
        ];

        $userVars = [];

        if ($request->input('mode') === 'single' && $request->filled('to_email')) {
            // Try to resolve a real user for the preview
            $user = \App\Models\User::where('email', $request->input('to_email'))->first();
            if ($user) {
                $userVars = $this->emailService->resolveUserVars($user);
            } else {
                // Unknown recipient — use the email address as a placeholder
                $userVars = [
                    'student_name' => $request->input('to_email'),
                    'teacher_name' => $request->input('to_email'),
                    'name'         => $request->input('to_email'),
                    'email'        => $request->input('to_email'),
                ];
            }
        } elseif ($request->input('mode') === 'group' && $request->filled('recipients')) {
            // Use the first resolved recipient as a sample for preview
            $sampleUsers = $this->emailService->resolveRecipients($request->input('recipients'));
            $sampleUser  = $sampleUsers->first();
            if ($sampleUser) {
                $userVars = $this->emailService->resolveUserVars($sampleUser);
            }
        }

        // Merge order: system < user < admin (admin overrides everything)
        $mergedVars = array_merge($systemVars, $userVars, $adminVars);

        // Render using the existing EmailTemplate::render() — no changes to that method
        $rendered = $template->render($mergedVars);

        $recipientInfo = match($request->input('mode')) {
            'single' => $request->input('to_email', '(no email entered)'),
            'group'  => ScheduledEmail::$recipientLabels[$request->input('recipients', '')] ?? $request->input('recipients', ''),
            default  => '',
        };

        return response()->json([
            'subject'        => $rendered['subject'],
            'body_html'      => $rendered['bodyHtml'],
            'recipient_info' => $recipientInfo,
            'is_sample'      => $request->input('mode') === 'group',
        ]);
    }

    /**
     * Handle a compose form submission.
     *
     * The hidden form fields (subject, body_html) already contain the fully-rendered
     * content from the preview step — variables have been substituted.
     *
     * Single send: use the rendered content directly via EmailService::send().
     * Group send:  re-render the raw template per recipient, merging admin-provided
     *              vars with each user's auto-resolved vars, so every recipient gets
     *              a personalised copy that matches what was shown in the preview.
     */
    public function sendCompose(Request $request)
    {
        $request->validate([
            'mode'          => ['required', 'in:single,group'],
            'to_email'      => ['required_if:mode,single', 'nullable', 'email'],
            'recipients'    => ['required_if:mode,group', 'nullable', 'string'],
            'subject'       => ['required', 'string', 'max:500'],
            'body_html'     => ['required', 'string'],
            'template_slug' => ['nullable', 'string', 'exists:email_templates,slug'],
            'vars'          => ['nullable', 'array'],
            'vars.*'        => ['nullable', 'string', 'max:500'],
        ]);

        $adminVars    = $request->input('vars', []);
        $templateSlug = $request->input('template_slug');

        // ── Single recipient ──────────────────────────────────────────────
        // The hidden fields already hold the rendered subject + body from the
        // preview step, so send them exactly as-is.
        if ($request->input('mode') === 'single') {

            $this->emailService->send(
                $request->input('to_email'),
                '',
                $request->input('subject'),   // rendered by preview
                $request->input('body_html'), // rendered by preview
                'compose',
                $templateSlug,
                auth()->id(),
                true,       // queued
                'compose'
            );

            $this->activityLog->log(
                'compose_email_sent',
                "Composed email to {$request->input('to_email')}"
            );

            return redirect()->route('admin.email.sent')
                ->with('success', 'Email queued for delivery to ' . $request->input('to_email') . '.');
        }

        // ── Group send ────────────────────────────────────────────────────
        // Re-render the raw template per recipient so each person gets their
        // own personalised copy (student_name, email, etc. filled from DB).
        // Admin-provided vars (exam_name, result, …) are merged in for all.
        if ($templateSlug) {
            $template = EmailTemplate::where('slug', $templateSlug)->first();
        } else {
            $template = null;
        }

        $systemVars = [
            'app_name' => config('app.name'),
            'app_url'  => config('app.url'),
            'year'     => now()->year,
        ];

        $users = $this->emailService->resolveRecipients($request->input('recipients'));
        $count = 0;

        foreach ($users as $user) {
            if (!$user->email) continue;

            // Per-recipient user vars (name, email, year_level, course_name, …)
            $userVars = $this->emailService->resolveUserVars($user);

            // Merge order: system < user < admin (admin overrides everything)
            $mergedVars = array_merge($systemVars, $userVars, $adminVars);

            if ($template) {
                // Render the template with merged vars for this specific recipient
                $rendered = $template->render($mergedVars);
                $subject  = $rendered['subject'];
                $body     = $rendered['bodyHtml'];
            } else {
                // No template — substitute vars into the raw subject/body from the form
                $subject = $this->emailService->substituteVars($request->input('subject'), $mergedVars);
                $body    = $this->emailService->substituteVars($request->input('body_html'), $mergedVars);
            }

            $this->emailService->send(
                $user->email,
                $user->name,
                $subject,
                $body,
                'compose',
                $templateSlug,
                $user->id,
                true,       // queued
                'compose'
            );
            $count++;
        }

        $this->activityLog->log(
            'compose_bulk_sent',
            "Composed bulk email to {$request->input('recipients')} ({$count} recipients)"
        );

        return redirect()->route('admin.email.sent')
            ->with('success', "{$count} email(s) queued for delivery.");
    }

    // ── Sent ─────────────────────────────────────────────────────────

    /**
     * AJAX preview for custom single-recipient message.
     * Renders the manual-message branded wrapper with the provided subject/body
     * and returns the full HTML — no email is sent, no log created.
     *
     * POST /admin/email/compose/custom/preview
     * Body: { subject, body }
     * Returns: JSON { html }
     */
    public function customPreview(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        $html = view('emails.manual-message', [
            'subject' => $request->input('subject'),
            'body'    => $request->input('body'),
            'sentAt'  => now(),
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Single-recipient custom email.
     *
     * Admin manually enters: to_email, subject, body (plain text).
     * Body is injected into the branded manual-message.blade.php wrapper
     * via view()->render() before being passed to EmailService::send(),
     * which queues it through the existing SendEmailJob + SMTP flow.
     *
     * Does NOT use any EmailTemplate. Logs with email_type = 'manual'.
     */
    public function sendCustom(Request $request)
    {
        $data = $request->validate([
            'to_email' => ['required', 'email', 'max:255'],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string', 'max:10000'],
        ]);

        // Render the branded HTML wrapper — body is e() + nl2br'd inside the blade
        $bodyHtml = view('emails.manual-message', [
            'subject' => $data['subject'],
            'body'    => $data['body'],
            'sentAt'  => now(),
        ])->render();

        $this->emailService->send(
            $data['to_email'],
            '',                // recipient name unknown — to_email only
            $data['subject'],
            $bodyHtml,
            'manual_compose',  // event
            null,              // no template slug
            auth()->id(),
            true,              // queued via SendEmailJob
            'manual'           // email_type
        );

        $this->activityLog->log(
            'manual_email_sent',
            "Admin sent manual email to {$data['to_email']} — Subject: {$data['subject']}"
        );

        return redirect()->route('admin.email.sent')
            ->with('success', "Email queued for delivery to {$data['to_email']}.");
    }

    /**
     * Show email_logs filtered to status = 'sent', with optional search.
     */
    public function sent(Request $request)
    {
        $query = EmailLog::where('status', 'sent')->latest('sent_at');

        if ($request->filled('email')) {
            $query->where('to_email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('type')) {
            $query->where('email_type', $request->input('type'));
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.email.sent', compact('logs'));
    }

    // ── Outbox ───────────────────────────────────────────────────────

    /**
     * Pending emails from two sources:
     *   1. email_logs where status = 'queued'  (dispatched but worker hasn't processed yet)
     *   2. scheduled_emails where is_sent = false  (future-dated sends)
     *
     * Does NOT read the jobs table.
     */
    public function outbox()
    {
        $queued    = EmailLog::where('status', 'queued')->latest()->paginate(20, ['*'], 'queued_page');
        $scheduled = ScheduledEmail::where('is_sent', false)->orderBy('send_at')->paginate(20, ['*'], 'sched_page');

        return view('admin.email.outbox', compact('queued', 'scheduled'));
    }

    // ── Bulk Email ───────────────────────────────────────────────────

    public function bulk()
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();
        $groups    = ScheduledEmail::$recipientLabels;

        return view('admin.email.bulk', compact('templates', 'groups'));
    }

    public function sendBulk(Request $request)
    {
        $data = $request->validate([
            'recipients'    => 'required|string',
            'subject'       => 'required|string|max:255',
            'body_html'     => 'required|string',
            'template_slug' => 'nullable|string|exists:email_templates,slug',
        ]);

        // If a template was chosen, use its RAW subject + body (not rendered).
        // Per-recipient variable substitution happens inside EmailService::sendBulk()
        // so each recipient gets their own personalised copy.
        if (!empty($data['template_slug'])) {
            $tmpl = EmailTemplate::findBySlug($data['template_slug']);
            if ($tmpl) {
                $data['subject']   = $tmpl->subject;
                $data['body_html'] = $tmpl->body_html;
            }
        }

        $count = $this->emailService->sendBulk(
            $data['recipients'],
            $data['subject'],
            $data['body_html'],
            'bulk_send',
            $data['template_slug'] ?? null
        );

        $this->activityLog->log('bulk_email_sent', "Sent bulk email to {$data['recipients']} ({$count} recipients)");

        return back()->with('success', "{$count} email(s) queued for delivery.");
    }

    // ── Scheduled Email ──────────────────────────────────────────────

    /**
     * Show the Academic Notification Scheduler page.
     * Passes all filter data needed to build the checkboxes.
     */
    public function scheduled()
    {
        $scheduled    = ScheduledEmail::with('creator')->latest()->paginate(20);
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels   = YearLevel::orderBy('level')->get();
        $majors       = Major::where('is_active', true)->orderBy('name')->get();
        $exams        = Exam::with('course')
                            ->whereIn('status', ['published', 'approved'])
                            ->orderByDesc('created_at')
                            ->get();

        return view('admin.email.scheduled', compact(
            'scheduled',
            'academicYears',
            'yearLevels',
            'majors',
            'exams'
        ));
    }

    /**
     * Store a new academic notification schedule.
     */
    public function storeScheduled(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'notification_type'     => 'required|in:exam_time,exam_policy,exam_reminder',
            'filter_academic_years' => 'nullable|array',
            'filter_academic_years.*' => 'integer|exists:academic_years,id',
            'filter_year_levels'    => 'nullable|array',
            'filter_year_levels.*'  => 'integer|exists:year_levels,id',
            'filter_majors'         => 'nullable|array',
            'filter_majors.*'       => 'integer|exists:majors,id',
            'exam_ids'              => 'nullable|array',
            'exam_ids.*'            => 'integer|exists:exams,id',
            'send_at'               => 'required|date|after:now',
        ]);

        ScheduledEmail::create([
            'name'                  => $data['name'],
            'notification_type'     => $data['notification_type'],
            'filter_academic_years' => $data['filter_academic_years'] ?? [],
            'filter_year_levels'    => $data['filter_year_levels'] ?? [],
            'filter_majors'         => $data['filter_majors'] ?? [],
            'exam_ids'              => $data['exam_ids'] ?? [],
            'send_at'               => $data['send_at'],
            'created_by'            => auth()->id(),
        ]);

        $this->activityLog->log(
            'scheduled_email_created',
            "Scheduled academic notification: {$data['name']} ({$data['notification_type']})"
        );

        return back()->with('success', 'Academic notification scheduled successfully.');
    }

    public function destroyScheduled(ScheduledEmail $scheduled)
    {
        if ($scheduled->is_sent) {
            return back()->withErrors(['error' => 'Cannot delete an already-sent scheduled email.']);
        }
        $scheduled->delete();
        return back()->with('success', 'Scheduled email cancelled.');
    }

    // ── Test Email ───────────────────────────────────────────────────

    public function testEmail()
    {
        return view('admin.email.test');
    }

    public function sendTestEmail(Request $request)
    {
        $data = $request->validate([
            'to_email' => 'required|email',
            'subject'  => 'required|string|max:255',
            'body'     => 'required|string',
        ]);

        $log = $this->emailService->send(
            $data['to_email'],
            'Test Recipient',
            $data['subject'],
            nl2br(e($data['body'])),
            'test_email',
            null,
            auth()->id(),
            false // send immediately (sync) for test
        );

        $this->activityLog->log('test_email_sent', "Sent test email to {$data['to_email']}");

        if ($log->status === 'sent') {
            return back()->with('success', "Test email sent successfully to {$data['to_email']}.");
        }

        return back()->withErrors(['error' => 'Test email failed: ' . $log->error]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function writeEnvValues(array $values): void
    {
        $envPath    = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $pattern     = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = $key . '=' . $value;

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}






<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ScheduledEmail;
use App\Services\ActivityLogService;
use App\Services\EmailService;
use App\Services\InboxSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class EmailController extends Controller
{
    public function __construct(
        private EmailService      $emailService,
        private ActivityLogService $activityLog,
        private InboxSyncService  $inboxSync
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
     * Inbox list — paginated, searchable, with unread badge count.
     */
    public function inbox(Request $request)
    {
        $query = \App\Models\InboxEmail::latest('received_at');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('from_email', 'like', '%'.$s.'%')
                  ->orWhere('from_name',  'like', '%'.$s.'%')
                  ->orWhere('subject',    'like', '%'.$s.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $emails      = $query->paginate(25)->withQueryString();
        $unreadCount = \App\Models\InboxEmail::where('status', 'unread')->count();

        return view('admin.email.inbox', compact('emails', 'unreadCount'));
    }

    /**
     * Show a single inbox email. Automatically marks it as read.
     */
    public function showInbox(\App\Models\InboxEmail $inboxEmail)
    {
        if ($inboxEmail->status === 'unread') {
            $inboxEmail->update(['status' => 'read']);
        }

        return view('admin.email.inbox.show', compact('inboxEmail'));
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
     * Reply to an inbox email.
     * Uses existing EmailService::send() + SendEmailJob — no new sending logic.
     * Creates an email_logs record automatically via EmailService.
     * Updates inbox_emails: status=replied, replied_by, replied_at.
     */
    public function replyInbox(Request $request, \App\Models\InboxEmail $inboxEmail)
    {
        $request->validate([
            'reply_body' => ['required', 'string', 'min:5'],
            'subject'    => ['nullable', 'string', 'max:255'],
        ]);

        $subject = $request->input('subject')
            ?: 'Re: ' . $inboxEmail->subject;

        $bodyHtml = nl2br(e($request->input('reply_body')));

        // Use existing EmailService — not touched
        $this->emailService->send(
            $inboxEmail->from_email,
            $inboxEmail->from_name ?: '',
            $subject,
            $bodyHtml,
            'inbox_reply',
            null,
            auth()->id(),
            true,           // queued via SendEmailJob
            'inbox_reply'
        );

        // Update inbox record
        $inboxEmail->update([
            'status'     => 'replied',
            'replied_by' => auth()->id(),
            'replied_at' => now(),
        ]);

        $this->activityLog->log(
            'inbox_reply_sent',
            "Replied to inbox email #{$inboxEmail->id} from {$inboxEmail->from_email}"
        );

        return redirect()->route('admin.email.inbox')
            ->with('success', "Reply queued for delivery to {$inboxEmail->from_email}.");
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
     * Sync inbox from Gmail via IMAP.
     * Fetches the most recent messages and stores new ones in inbox_emails.
     * Existing records (matched by message_id) are skipped — no duplicates.
     */
    public function syncInbox(Request $request)
    {
        try {
            $result = $this->inboxSync->sync();

            $this->activityLog->log(
                'inbox_synced',
                "Inbox sync: {$result['imported']} imported, {$result['skipped']} skipped, {$result['errors']} errors."
            );

            if ($result['errors'] > 0 && $result['imported'] === 0) {
                return redirect()->route('admin.email.inbox')
                    ->withErrors(['error' => 'Sync failed: ' . $result['message']]);
            }

            return redirect()->route('admin.email.inbox')
                ->with('success', $result['message']);

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
        $groups    = ScheduledEmail::$recipientLabels;

        return view('admin.email.outbox', compact('queued', 'scheduled', 'groups'));
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

    public function scheduled()
    {
        $scheduled = ScheduledEmail::latest()->paginate(20);
        $groups    = ScheduledEmail::$recipientLabels;
        return view('admin.email.scheduled', compact('scheduled', 'groups'));
    }

    public function storeScheduled(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'subject'    => 'required|string|max:255',
            'body_html'  => 'required|string',
            'recipients' => 'required|string',
            'send_at'    => 'required|date|after:now',
        ]);

        ScheduledEmail::create([...$data, 'created_by' => auth()->id()]);
        $this->activityLog->log('scheduled_email_created', "Scheduled email: {$data['name']}");

        return back()->with('success', 'Email scheduled.');
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






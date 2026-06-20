<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ScheduledEmail;
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendExamTimetableNotificationJob;
use App\Models\AcademicYear;
use App\Models\EmailLog;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamTimetableNotification;
use App\Models\Major;
use App\Models\StudentYearRecord;
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

        return view('admin.email.index', compact('stats', 'recentLogs'));
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
     * Returns rendered inbox table rows + pagination + counts as JSON.
     * Called by the AJAX auto-refresh to replace the table body in-place,
     * preventing duplicate rows and ensuring the displayed count matches DB.
     *
     * GET /admin/email/inbox/rows?page=<n>&search=<s>&status=<s>
     */
    public function inboxRows(Request $request)
    {
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
            ->whereNull('parent_id')
            ->orderByDesc('received_at');

        $emails      = $query->paginate(25)->withQueryString();
        $unreadCount = \App\Models\InboxEmail::where('status', 'unread')->count();

        $threadIds    = $emails->pluck('thread_id')->filter()->unique()->values()->toArray();
        $threadCounts = [];
        if (!empty($threadIds)) {
            $threadCounts = \App\Models\InboxEmail::whereIn('thread_id', $threadIds)
                ->selectRaw('thread_id, COUNT(*) as cnt')
                ->groupBy('thread_id')
                ->pluck('cnt', 'thread_id')
                ->toArray();
        }

        $rowsHtml       = view('admin.email.inbox-rows', compact('emails', 'threadCounts'))->render();
        $paginationHtml = $emails->hasPages()
            ? view('admin.email.inbox-pagination', compact('emails'))->render()
            : '';

        return response()->json([
            'rows_html'       => $rowsHtml,
            'pagination_html' => $paginationHtml,
            'unread_count'    => $unreadCount,
            'total'           => $emails->total(),
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
     *
     * Responds with JSON when called via AJAX (X-Requested-With header), or
     * redirects when called via a normal form POST.
     */
    public function syncInbox(Request $request)
    {
        // Remove the 60 s wall-clock limit for this admin request only.
        // The InboxSyncService fetch limit (default 20 msgs) keeps this fast.
        @set_time_limit(0);

        $isAjax = $request->ajax() || $request->wantsJson();

        $syncService = app(\App\Services\InboxSyncService::class);

        try {
            $result = $syncService->sync();

            $this->activityLog->log(
                'inbox_synced',
                "Inbox sync: {$result['imported']} imported, {$result['skipped']} skipped, {$result['errors']} errors."
            );

            if ($result['errors'] > 0 && $result['imported'] === 0) {
                if ($isAjax) {
                    return response()->json(['ok' => false, 'message' => 'Sync failed: ' . $result['message']], 500);
                }
                return redirect()->route('admin.email.inbox')
                    ->withErrors(['error' => 'Sync failed: ' . $result['message']]);
            }

            $msg = $result['imported'] > 0
                ? "{$result['imported']} new email(s) imported. {$result['skipped']} already existed."
                : "Sync complete — no new emails. ({$result['skipped']} already existed)";

            if ($isAjax) {
                return response()->json(['ok' => true, 'message' => $msg, 'imported' => $result['imported']]);
            }

            return redirect()->route('admin.email.inbox')
                ->with('success', $msg);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('EmailController::syncInbox — ' . $e->getMessage());
            if ($isAjax) {
                return response()->json(['ok' => false, 'message' => 'Sync error: ' . $e->getMessage()], 500);
            }
            return redirect()->route('admin.email.inbox')
                ->withErrors(['error' => 'Sync error: ' . $e->getMessage()]);
        }
    }

    // ── Compose ──────────────────────────────────────────────────────

    public function compose()
    {
        $groups = EmailService::recipientLabels();

        // ── Timetable notification filter data ────────────────────────────
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();
        $majors        = Major::where('is_active', true)->orderBy('name')->get();

        return view('admin.email.compose', compact('groups', 'academicYears', 'yearLevels', 'majors'));
    }

    /**
     * AJAX endpoint — previews a group or single send.
     * Substitutes system + user variables into the provided raw subject/body.
     * Does NOT send email or create any log.
     *
     * POST /admin/email/compose/preview
     * Body: subject, body_html, mode, to_email (optional), recipients (optional)
     * Returns: JSON { subject, body_html, recipient_info }
     */
    public function composePreview(Request $request)
    {
        $request->validate([
            'subject'    => ['required', 'string', 'max:500'],
            'body_html'  => ['required', 'string'],
            'mode'       => ['required', 'in:single,group'],
            'to_email'   => ['nullable', 'email'],
            'recipients' => ['nullable', 'string'],
        ]);

        $systemVars = [
            'app_name' => config('app.name'),
            'app_url'  => config('app.url'),
            'year'     => now()->year,
        ];

        $userVars = [];

        if ($request->input('mode') === 'single' && $request->filled('to_email')) {
            $user = \App\Models\User::where('email', $request->input('to_email'))->first();
            if ($user) {
                $userVars = $this->emailService->resolveUserVars($user);
            } else {
                $userVars = [
                    'student_name' => $request->input('to_email'),
                    'teacher_name' => $request->input('to_email'),
                    'name'         => $request->input('to_email'),
                    'email'        => $request->input('to_email'),
                ];
            }
        } elseif ($request->input('mode') === 'group' && $request->filled('recipients')) {
            $sampleUser = $this->emailService->resolveRecipients($request->input('recipients'))->first();
            if ($sampleUser) {
                $userVars = $this->emailService->resolveUserVars($sampleUser);
            }
        }

        $mergedVars  = array_merge($systemVars, $userVars);
        $subject     = $this->emailService->substituteVars($request->input('subject'), $mergedVars);
        $bodyHtml    = $this->emailService->substituteVars($request->input('body_html'), $mergedVars);
        $groups      = EmailService::recipientLabels();

        $recipientInfo = match($request->input('mode')) {
            'single' => $request->input('to_email', '(no email entered)'),
            'group'  => $groups[$request->input('recipients', '')] ?? $request->input('recipients', ''),
            default  => '',
        };

        return response()->json([
            'subject'        => $subject,
            'body_html'      => $bodyHtml,
            'recipient_info' => $recipientInfo,
            'is_sample'      => $request->input('mode') === 'group',
        ]);
    }

    /**
     * Handle a compose form submission.
     *
     * Single send: send the subject/body as-is to the specified address.
     * Group send:  substitute per-recipient user vars into the raw subject/body
     *              so each person gets a personalised copy.
     */
    public function sendCompose(Request $request)
    {
        $request->validate([
            'mode'       => ['required', 'in:single,group'],
            'to_email'   => ['required_if:mode,single', 'nullable', 'email'],
            'recipients' => ['required_if:mode,group', 'nullable', 'string'],
            'subject'    => ['required', 'string', 'max:500'],
            'body_html'  => ['required', 'string'],
        ]);

        // ── Single recipient ──────────────────────────────────────────────
        if ($request->input('mode') === 'single') {

            $this->emailService->send(
                $request->input('to_email'),
                '',
                $request->input('subject'),
                $request->input('body_html'),
                'compose',
                null,
                auth()->id(),
                true,
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
        // Substitute per-recipient vars into the raw subject/body for each user.
        $systemVars = [
            'app_name' => config('app.name'),
            'app_url'  => config('app.url'),
            'year'     => now()->year,
        ];

        $users = $this->emailService->resolveRecipients($request->input('recipients'));
        $count = 0;

        foreach ($users as $user) {
            if (!$user->email) continue;

            $userVars   = $this->emailService->resolveUserVars($user);
            $mergedVars = array_merge($systemVars, $userVars);

            $subject = $this->emailService->substituteVars($request->input('subject'), $mergedVars);
            $body    = $this->emailService->substituteVars($request->input('body_html'), $mergedVars);

            $this->emailService->send(
                $user->email,
                $user->name,
                $subject,
                $body,
                'compose',
                null,
                $user->id,
                true,
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
     * Pending emails:
     *   email_logs where status = 'queued'  (dispatched but worker hasn't processed yet)
     *
     * Does NOT read the jobs table.
     */
    public function outbox()
    {
        $queued = EmailLog::where('status', 'queued')->latest()->paginate(20);

        return view('admin.email.outbox', compact('queued'));
    }

    // ── Bulk Email ───────────────────────────────────────────────────

    public function bulk()
    {
        $groups = EmailService::recipientLabels();
        return view('admin.email.bulk', compact('groups'));
    }

    public function sendBulk(Request $request)
    {
        $data = $request->validate([
            'recipients' => 'required|string',
            'subject'    => 'required|string|max:255',
            'body_html'  => 'required|string',
        ]);

        $count = $this->emailService->sendBulk(
            $data['recipients'],
            $data['subject'],
            $data['body_html'],
            'bulk_send'
        );

        $this->activityLog->log('bulk_email_sent', "Sent bulk email to {$data['recipients']} ({$count} recipients)");

        return back()->with('success', "{$count} email(s) queued for delivery.");
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

    // ── Exam Timetable Notification ───────────────────────────────────────

    /**
     * AJAX — GET filtered exam schedules for the Timetable Notification panel.
     *
     * Finds exam schedules for published/approved exams whose course matches
     * the selected year_level, semester and (optionally) major.
     *
     * Note: academic_year_id is stored on the notification log for record-keeping,
     * but we do NOT filter courses by academic_year_id here because:
     *  - Courses are associated to a specific academic year at creation time.
     *  - Exams / schedules are created against those courses.
     *  - The admin selects the academic year to define WHICH STUDENTS receive the
     *    email, not to filter which exams appear.
     *  - Exams are shown based on year_level + semester + major only.
     *
     * GET /admin/email/timetable/schedules
     */
    public function timetableSchedules(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'year_level_id'    => ['required', 'integer', 'exists:year_levels,id'],
            'major_id'         => ['nullable', 'integer', 'exists:majors,id'],
            'semester'         => ['required', 'integer', 'in:1,2'],
        ]);

        $yearLevelId = (int) $request->input('year_level_id');
        $majorId     = $request->filled('major_id') ? (int) $request->input('major_id') : null;
        $semester    = (int) $request->input('semester');

        // Resolve the integer level value (1–5) for the course query
        $yearLevel = YearLevel::findOrFail($yearLevelId);

        // ── Build course filter ───────────────────────────────────────────
        // Match courses by year_level integer and semester.
        // year_level 0 = "all year levels", semester 0 = "both semesters".
        // We do NOT filter by academic_year_id — courses live in one AY but
        // exams for that course are relevant to students regardless of which
        // AY the admin is targeting for the notification.
        $courseQuery = \App\Models\Course::query()
            ->where(function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel->level)
                  ->orWhere('year_level', 0); // 0 = all years
            })
            ->where(function ($q) use ($semester) {
                $q->where('semester', $semester)
                  ->orWhere('semester', 0); // 0 = both semesters
            });

        // Major filter — if a major is selected, include courses for that major
        // OR courses with no major restriction (null = available to all majors).
        if ($majorId !== null) {
            $courseQuery->where(function ($q) use ($majorId) {
                $q->where('major_id', $majorId)
                  ->orWhereNull('major_id');
            });
        }
        // If no major selected (year 1), no additional major restriction needed —
        // year 1 courses typically have a major_id assigned to the shared major,
        // so we simply rely on year_level=1 filter already applied above.

        $courseIds = $courseQuery->pluck('id');

        if ($courseIds->isEmpty()) {
            return response()->json(['schedules' => []]);
        }

        // ── Get published/approved exam schedules for these courses ───────
        $schedules = ExamSchedule::query()
            ->whereHas('exam', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds)
                  ->whereIn('status', ['published', 'approved'])
                  ->whereNull('deleted_at');
            })
            ->with(['exam.course'])
            ->get()
            ->map(function (ExamSchedule $s) {
                return [
                    'id'             => $s->id,
                    'subject'        => $s->exam->title . ' — ' . ($s->exam->course?->title ?? '—'),
                    'course'         => $s->exam->course?->title ?? '—',
                    'exam_title'     => $s->exam->title,
                    'start_datetime' => $s->starts_at->format('d M Y h:i A'),
                    'end_datetime'   => $s->ends_at->format('d M Y h:i A'),
                    'allowed_time'   => $s->duration_minutes,
                    'attempt_count'  => $s->attempt_limit,
                ];
            })
            ->values();

        return response()->json(['schedules' => $schedules]);
    }

    /**
     * AJAX — Preview the Exam Timetable email HTML.
     *
     * POST /admin/email/timetable/preview
     */
    public function timetablePreview(Request $request)
    {
        $request->validate([
            'academic_year_id'        => ['required', 'integer', 'exists:academic_years,id'],
            'year_level_id'           => ['required', 'integer', 'exists:year_levels,id'],
            'major_id'                => ['nullable', 'integer', 'exists:majors,id'],
            'semester'                => ['required', 'integer', 'in:1,2'],
            'schedule_ids'            => ['required', 'array', 'min:1'],
            'schedule_ids.*'          => ['integer', 'exists:exam_schedules,id'],
            'exam_policy'             => ['nullable', 'string', 'max:5000'],
            'additional_instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $academicYear = AcademicYear::findOrFail($request->input('academic_year_id'));
        $yearLevel    = YearLevel::findOrFail($request->input('year_level_id'));
        $major        = $request->filled('major_id') ? Major::find($request->input('major_id')) : null;
        $semester     = (int) $request->input('semester');

        // Build exam rows for the selected schedule IDs
        $schedules = ExamSchedule::with(['exam.course'])
            ->whereIn('id', $request->input('schedule_ids'))
            ->get();

        $exams = $schedules->values()->map(function (ExamSchedule $s, int $idx) {
            return [
                'no'             => $idx + 1,
                'subject'        => $s->exam->title . ' — ' . ($s->exam->course?->title ?? '—'),
                'course'         => $s->exam->course?->title ?? '—',
                'exam_title'     => $s->exam->title,
                'start_datetime' => $s->starts_at->format('d M Y h:i A'),
                'end_datetime'   => $s->ends_at->format('d M Y h:i A'),
                'allowed_time'   => $s->duration_minutes,
                'attempt_count'  => $s->attempt_limit,
            ];
        })->all();

        $html = view('emails.exam-timetable', [
            'studentName'            => 'Sample Student',
            'academicYearName'       => $academicYear->name,
            'yearLevelName'          => $yearLevel->name,
            'majorName'              => $major?->name,
            'semesterLabel'          => 'Semester ' . $semester,
            'exams'                  => $exams,
            'examPolicy'             => $request->input('exam_policy'),
            'additionalInstructions' => $request->input('additional_instructions'),
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Send Exam Timetable Notification emails.
     *
     * Resolves students from StudentYearRecord matching the selected academic
     * group filters, then dispatches one SendExamTimetableNotificationJob per
     * student via the existing 'emails' queue.
     *
     * POST /admin/email/timetable/send
     */
    public function sendTimetableNotification(Request $request)
    {
        $data = $request->validate([
            'academic_year_id'        => ['required', 'integer', 'exists:academic_years,id'],
            'year_level_id'           => ['required', 'integer', 'exists:year_levels,id'],
            'major_id'                => ['nullable', 'integer', 'exists:majors,id'],
            'semester'                => ['required', 'integer', 'in:1,2'],
            'schedule_ids'            => ['required', 'array', 'min:1'],
            'schedule_ids.*'          => ['integer', 'exists:exam_schedules,id'],
            'exam_policy'             => ['nullable', 'string', 'max:5000'],
            'additional_instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
        $yearLevel    = YearLevel::findOrFail($data['year_level_id']);
        $major        = isset($data['major_id']) ? Major::find($data['major_id']) : null;
        $semester     = (int) $data['semester'];
        $semesterLabel = 'Semester ' . $semester;

        // ── 1. Resolve recipient students ────────────────────────────────
        $majorIds = $major ? [$major->id] : [];

        $students = $this->emailService->resolveAcademicRecipients(
            [$academicYear->id],
            [$yearLevel->id],
            $majorIds
        );

        // Additionally filter by semester (StudentYearRecord stores semester as string)
        $students = $students->filter(function ($student) use ($semester, $academicYear, $yearLevel, $major) {
            // Check that the student has an active record for this specific semester
            return StudentYearRecord::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('year_level_id', $yearLevel->id)
                ->where('semester', (string) $semester)
                ->where('status', 'active')
                ->when($major, function ($q) use ($major) {
                    $q->where('major', $major->name);
                })
                ->exists();
        });

        if ($students->isEmpty()) {
            return redirect()->route('admin.email.compose')
                ->withErrors(['error' => 'No active students found for the selected academic group and semester.'])
                ->withInput();
        }

        // ── 2. Build exam rows (same for all recipients) ──────────────────
        $schedules = ExamSchedule::with(['exam.course'])
            ->whereIn('id', $data['schedule_ids'])
            ->get();

        $exams = $schedules->values()->map(function (ExamSchedule $s, int $idx) {
            return [
                'no'             => $idx + 1,
                'subject'        => $s->exam->title . ' — ' . ($s->exam->course?->title ?? '—'),
                'course'         => $s->exam->course?->title ?? '—',
                'exam_title'     => $s->exam->title,
                'start_datetime' => $s->starts_at->format('d M Y h:i A'),
                'end_datetime'   => $s->ends_at->format('d M Y h:i A'),
                'allowed_time'   => $s->duration_minutes,
                'attempt_count'  => $s->attempt_limit,
            ];
        })->all();

        // ── 3. Create the batch notification log ──────────────────────────
        $notification = ExamTimetableNotification::create([
            'sent_by'                 => auth()->id(),
            'academic_year_id'        => $academicYear->id,
            'year_level_id'           => $yearLevel->id,
            'major_id'                => $major?->id,
            'semester'                => $semester,
            'exam_schedule_ids'       => array_values($data['schedule_ids']),
            'exam_policy'             => $data['exam_policy'] ?? null,
            'additional_instructions' => $data['additional_instructions'] ?? null,
            'recipient_count'         => $students->count(),
            'status'                  => 'queued',
            'sent_at'                 => now(),
        ]);

        // ── 4. Dispatch one job per student ───────────────────────────────
        $count = 0;
        foreach ($students as $student) {
            if (!$student->email) {
                continue;
            }

            SendExamTimetableNotificationJob::dispatch(
                $student->id,
                $student->name,
                $student->email,
                $academicYear->name,
                $yearLevel->name,
                $major?->name,
                $semesterLabel,
                $exams,
                $data['exam_policy'] ?? null,
                $data['additional_instructions'] ?? null,
                $notification->id
            );

            $count++;
        }

        // Update notification status
        $notification->update(['status' => 'sent', 'recipient_count' => $count]);

        $this->activityLog->log(
            'exam_timetable_notification_sent',
            "Exam Timetable Notification sent to {$count} students — "
            . "{$academicYear->name} · {$yearLevel->name}"
            . ($major ? ' · ' . $major->name : '')
            . " · Semester {$semester}"
            . " · " . count($data['schedule_ids']) . " exam(s)"
        );

        return redirect()->route('admin.email.sent')
            ->with('success', "Exam Timetable Notification queued for {$count} student(s).");
    }

    /**
     * Show the Exam Timetable Notification history log.
     *
     * GET /admin/email/timetable/logs
     */
    public function timetableLogs(Request $request)
    {
        $logs = ExamTimetableNotification::with(['sender', 'academicYear', 'yearLevel', 'major'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.email.timetable-logs', compact('logs'));
    }
}






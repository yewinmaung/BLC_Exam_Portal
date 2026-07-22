<?php

namespace App\Services;

use App\Models\InboxEmail;
use App\Models\User;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;

/**
 * InboxSyncService
 *
 * Fetches the most-recent N messages from the configured INBOX via IMAP
 * (Webklex Laravel-IMAP 6.2) and stores them in inbox_emails.
 *
 * Timeout fix summary
 * -------------------
 * The original code called ->all() which instructed Webklex to download the
 * full body+attachments of EVERY message in the mailbox before our take()
 * cap had any effect.  With a large mailbox this easily exceeds 60 s.
 *
 * Fix:
 *  1. setFetchBody(false) / setFetchFlags(false) on the query so only
 *     lightweight envelope data (headers + UID) is transferred for the
 *     initial listing.
 *  2. limit(FETCH_LIMIT) tells the IMAP server to return at most N UIDs,
 *     so the wire transfer is bounded before a single byte of body is read.
 *  3. For messages that survive the dedup check we call
 *     $message->parseBody() to load only the bodies we actually need.
 *
 * Rules that are NOT changed
 * --------------------------
 *  - Never touches SendEmailJob, OTP jobs, EmailService send flow, or SMTP.
 *  - Never modifies the remote mailbox (FT_PEEK remains in config/imap.php).
 *  - Does not create new database tables.
 */
class InboxSyncService
{
    /**
     * Maximum number of messages fetched per sync run (most recent first).
     * Override via IMAP_SYNC_LIMIT in .env.
     */
    private int $fetchLimit;

    public function __construct()
    {
        $this->fetchLimit = (int) env('IMAP_SYNC_LIMIT', 20);
        if ($this->fetchLimit < 1 || $this->fetchLimit > 200) {
            $this->fetchLimit = 20;
        }
    }

    /**
     * Connect to IMAP, fetch the latest messages, persist new ones.
     *
     * @return array{imported: int, skipped: int, errors: int, message: string}
     */
    public function sync(): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = 0;

        try {
            // ── 1. Connect ────────────────────────────────────────────────
            $client = Client::account('default');
            $client->connect();

            // ── 2. Open INBOX ─────────────────────────────────────────────
            $folder = $client->getFolderByName('INBOX');

            if (!$folder) {
                $client->disconnect();
                return [
                    'imported' => 0,
                    'skipped'  => 0,
                    'errors'   => 1,
                    'message'  => 'INBOX folder not found.',
                ];
            }

            // ── 3. Fetch envelope-only, newest first, bounded to FETCH_LIMIT
            //
            //  KEY CHANGE: setFetchBody(false) + setFetchFlags(false) tell
            //  Webklex not to download message bodies during the listing
            //  phase. limit() caps the number of UIDs the server returns so
            //  the IMAP wire transfer is O(fetchLimit) regardless of mailbox
            //  size.  Full body is fetched later only for new messages.
            // ──────────────────────────────────────────────────────────────
            $messages = $folder->messages()
                ->all()
                ->setFetchBody(false)
                ->setFetchFlags(false)
                ->setFetchOrder('desc')
                ->limit($this->fetchLimit)
                ->get();

            // ── 4. Process each message ───────────────────────────────────
            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($message);
                    if ($result === 'imported') {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('InboxSyncService: failed to process message — ' . $e->getMessage());
                }
            }

            $client->disconnect();

        } catch (\Throwable $e) {
            Log::error('InboxSyncService::sync() connection error — ' . $e->getMessage());
            return [
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors + 1,
                'message'  => 'IMAP connection error: ' . $e->getMessage(),
            ];
        }

        $msg = "Sync complete: {$imported} imported, {$skipped} already existed, {$errors} errors.";

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => $msg,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Process one IMAP message.
     *
     * Because the query used setFetchBody(false), envelope headers are already
     * present (From, Subject, Date, Message-ID) but the body is not.  We run
     * the dedup check first; only if the message is new do we call parseBody()
     * to download the body, avoiding wasted bandwidth on duplicates.
     *
     * @return 'imported'|'skipped'
     */
    private function processMessage(\Webklex\PHPIMAP\Message $message): string
    {
        // ── Extract Message-ID (available without body download) ───────────
        $messageId = $this->extractMessageId($message);

        // ── Fast dedup: skip if already stored ────────────────────────────
        if ($messageId && InboxEmail::where('message_id', $messageId)->exists()) {
            return 'skipped';
        }

        // ── Extract sender (from envelope, no body needed) ────────────────
        $fromCollection = $message->getFrom();
        $fromEmail      = '';
        $fromName       = null;

        if ($fromCollection && $fromCollection->count() > 0) {
            $firstFrom = $fromCollection->first();
            $fromEmail = $firstFrom->mail     ?? '';
            $fromName  = $firstFrom->personal ?? null;
            if (trim((string) $fromName) === '') {
                $fromName = null;
            }
        }

        if (empty($fromEmail)) {
            return 'skipped';
        }

        // ── Extract subject (envelope) ─────────────────────────────────────
        $subject = $this->safeString($message->getSubject()) ?: '(no subject)';

        // ── Extract received date (envelope) ──────────────────────────────
        $receivedAt = now();
        try {
            $date = $message->getDate();
            if ($date && $date->count() > 0) {
                $carbonDate = $date->first();
                if ($carbonDate instanceof \Carbon\Carbon) {
                    $receivedAt = $carbonDate;
                }
            }
        } catch (\Throwable $e) {
            // Use fallback date
        }

        // ── Fallback dedup key when no Message-ID ─────────────────────────
        if (!$messageId) {
            $messageId = 'fallback:' . md5($fromEmail . '|' . $subject . '|' . $receivedAt->toDateTimeString());

            if (InboxEmail::where('message_id', $messageId)->exists()) {
                return 'skipped';
            }
        }

        // ── Fetch body only for new messages ──────────────────────────────
        //   parseBody() performs a targeted IMAP FETCH for this single UID,
        //   so we only pay the download cost for messages we actually store.
        $bodyHtml = null;
        $bodyText = null;

        try {
            $message->parseBody();

            $htmlBodies = $message->getBodies();
            if (isset($htmlBodies['html'])) {
                $bodyHtml = (string) $htmlBodies['html'];
            }
            if (isset($htmlBodies['text'])) {
                $bodyText = (string) $htmlBodies['text'];
            }
        } catch (\Throwable $e) {
            // Body fetch failure is non-fatal — store without body
            Log::debug('InboxSyncService: body fetch failed — ' . $e->getMessage());
        }

        // ── Extract threading headers (available post-parseBody) ──────────
        $inReplyTo = null;
        try {
            $irt = $message->getInReplyTo();
            if ($irt) {
                $inReplyTo = $this->safeString($irt);
            }
        } catch (\Throwable $e) { /* non-fatal */ }

        // ── Determine sender_type and user_id ──────────────────────────────
        $user       = User::where('email', $fromEmail)->first();
        $senderType = ($user && $user->isStudent()) ? 'student' : 'external';
        $userId     = $user?->id;

        // ── Persist ────────────────────────────────────────────────────────
        InboxEmail::create([
            'from_email'  => $fromEmail,
            'from_name'   => $fromName,
            'sender_type' => $senderType,
            'user_id'     => $userId,
            'subject'     => mb_substr($subject, 0, 255),
            'body_html'   => $bodyHtml,
            'body_text'   => $bodyText,
            'message_id'  => mb_substr($messageId, 0, 255),
            'in_reply_to' => $inReplyTo ? mb_substr($inReplyTo, 0, 255) : null,
            'thread_id'   => null,
            'status'      => 'unread',
            'received_at' => $receivedAt,
        ]);

        return 'imported';
    }

    /**
     * Safely extract the Message-ID header string.
     */
    private function extractMessageId(\Webklex\PHPIMAP\Message $message): ?string
    {
        try {
            $mid = $message->getMessageId();
            if ($mid === null) {
                return null;
            }
            $str = $this->safeString($mid);
            return ($str !== '' && $str !== null) ? trim($str, '<> ') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert a Webklex attribute value to a plain PHP string safely.
     */
    private function safeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_array($value)) {
            $first = reset($value);
            return $first !== false ? $this->safeString($first) : null;
        }
        return null;
    }
}

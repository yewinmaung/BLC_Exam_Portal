<?php

namespace App\Services;

use App\Events\NewEmailReceived;
use App\Models\InboxEmail;
use App\Models\InboxSyncState;
use App\Models\User;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * InboxSyncService
 *
 * Fetches new messages from the configured INBOX via IMAP (Webklex
 * Laravel-IMAP 6.2) using a UID-based incremental cursor, resolves
 * conversation threads, persists new messages to inbox_emails, and
 * broadcasts NewEmailReceived for each import.
 *
 * Sync strategy — UID cursor
 * --------------------------
 * The last successfully persisted IMAP UID is stored in inbox_sync_state.
 * Each sync fetches only messages with UID > last_uid, so no email is ever
 * missed regardless of inbox volume.  Messages are processed in ascending
 * UID order; the checkpoint is advanced one message at a time.  If a
 * message fails, the loop stops immediately — the checkpoint stays at the
 * last good UID so the failed message is retried on the next run.
 *
 * Concurrency lock
 * ----------------
 * A Cache atomic lock ('imap_inbox_sync') is acquired at the top of sync().
 * Both the scheduled inbox:sync command and the manual InboxSyncJob enter
 * through sync(), so only one execution can run at any time.  A second
 * caller returns immediately with a "Sync already running" result.
 *
 * Thread resolution algorithm
 * ---------------------------
 * For each incoming message we resolve three things:
 *
 *  thread_id — A stable key shared by all messages in the same conversation.
 *              Built from the oldest known Message-ID in the References chain:
 *              md5(rootMessageId). If no References exist and In-Reply-To is
 *              missing, the message starts a new thread (md5 of its own ID).
 *
 *  parent_id — FK to inbox_emails.id of the immediate parent.  Found by
 *              looking up In-Reply-To in our local inbox_emails table.
 *
 *  references — Raw RFC 2822 References header, stored verbatim for future
 *               ancestry reconstruction.
 *
 * Nothing changed outside this service
 * -------------------------------------
 *  - SMTP, OTP, SendEmailJob, Academic Scheduler — untouched.
 *  - Does not modify the remote mailbox (FT_PEEK in config/imap.php).
 */
class InboxSyncService
{
    /**
     * Atomic lock key — shared between the scheduled command and the
     * manual InboxSyncJob so only one sync runs at a time.
     */
    private const LOCK_KEY     = 'imap_inbox_sync';

    /**
     * Lock TTL in seconds.  Acts as a dead-man's switch: if the process
     * dies without releasing the lock it auto-expires, allowing the next
     * scheduled run to proceed.  Must be longer than the worst-case sync
     * duration (InboxSyncJob timeout = 120 s).
     */
    private const LOCK_TTL     = 150;

    public function __construct()
    {
        // No fetch-limit property needed — UID-based fetch has no artificial cap.
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Connect to IMAP, fetch all messages with UID > last checkpoint,
     * persist new ones with threads, and advance the checkpoint.
     *
     * Returns a result summary consumed by both SyncInbox (Artisan) and
     * InboxSyncJob (queue) — the callers are unchanged.
     *
     * @return array{imported: int, skipped: int, errors: int, message: string}
     */
    public function sync(): array
    {
        // ── Concurrency lock ──────────────────────────────────────────────
        // Only one sync execution (scheduled or manual) may run at a time.
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (!$lock->get()) {
            Log::info('InboxSyncService: sync already running — skipping this call.');
            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => 0,
                'message'  => 'Sync already running — skipped.',
            ];
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = 0;

        try {
            // ── Read checkpoint ───────────────────────────────────────────
            $lastUid = InboxSyncState::readLastUid();

            // ── IMAP connection ───────────────────────────────────────────
            $client = Client::account('default');
            $client->connect();

            $folder = $client->getFolderByName('INBOX');
            if (!$folder) {
                $client->disconnect();
                $lock->release();
                return ['imported' => 0, 'skipped' => 0, 'errors' => 1,
                        'message'  => 'INBOX folder not found.'];
            }

            // ── UID-based incremental fetch ───────────────────────────────
            // Fetch only messages with UID > last checkpoint, oldest first.
            // setFetchBody(false) keeps this phase envelope-only; parseBody()
            // is called only for messages that pass dedup (in processMessage).
            $messages = $folder->messages()
                ->setFetchBody(false)
                ->setFetchFlags(false)
                ->setFetchOrder('asc')
                ->getByUidGreater($lastUid);

            // ── Process each new message ──────────────────────────────────
            // Ascending UID order means we process oldest-new first.
            // The checkpoint is advanced after EACH successful persist.
            // On the first failure the loop breaks — the failed UID will be
            // retried on the next sync run.
            foreach ($messages as $message) {
                $uid = (int) $message->uid;

                try {
                    $result = $this->processMessage($message);

                    if ($result === 'imported') {
                        $imported++;
                    } else {
                        $skipped++;
                    }

                    // Advance checkpoint regardless of imported/skipped.
                    // A "skipped" message (already in DB) still counts as
                    // processed — its UID must not be re-fetched next run.
                    if ($uid > $lastUid) {
                        InboxSyncState::saveLastUid($uid);
                        $lastUid = $uid;
                    }

                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning(
                        "InboxSyncService: failed to process UID {$uid} — " . $e->getMessage()
                    );
                    // Stop the batch immediately.  Checkpoint stays at $lastUid
                    // (the last successfully processed UID), so this UID is
                    // retried on the next scheduler run.
                    break;
                }
            }

            $client->disconnect();

        } catch (\Throwable $e) {
            Log::error('InboxSyncService::sync() — ' . $e->getMessage());
            $lock->release();
            return [
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors + 1,
                'message'  => 'IMAP connection error: ' . $e->getMessage(),
            ];
        }

        $lock->release();

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => "Sync complete: {$imported} imported, {$skipped} already existed, {$errors} errors.",
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Process one IMAP message: dedup → body fetch → thread resolve → persist → broadcast.
     *
     * @return 'imported'|'skipped'
     */
    private function processMessage(\Webklex\PHPIMAP\Message $message): string
    {
        // ── Message-ID ────────────────────────────────────────────────────
        $messageId = $this->extractMessageId($message);

        // ── Fast dedup ────────────────────────────────────────────────────
        if ($messageId && InboxEmail::where('message_id', $messageId)->exists()) {
            return 'skipped';
        }

        // ── Sender ────────────────────────────────────────────────────────
        $fromEmail = '';
        $fromName  = null;

        $fromCollection = $message->getFrom();
        if ($fromCollection && $fromCollection->count() > 0) {
            $first     = $fromCollection->first();
            $fromEmail = $first->mail     ?? '';
            $fromName  = $first->personal ?? null;
            if (trim((string) $fromName) === '') {
                $fromName = null;
            }
        }

        if (empty($fromEmail)) {
            return 'skipped';
        }

        // ── Subject + Date (envelope) ─────────────────────────────────────
        $subject    = $this->safeString($message->getSubject()) ?: '(no subject)';
        $receivedAt = $this->extractDate($message);

        // ── Fallback dedup when Message-ID is absent ──────────────────────
        if (!$messageId) {
            $messageId = 'fallback:' . md5($fromEmail . '|' . $subject . '|' . $receivedAt->toDateTimeString());
            if (InboxEmail::where('message_id', $messageId)->exists()) {
                return 'skipped';
            }
        }

        // ── Body (targeted fetch for new messages only) ───────────────────
        $bodyHtml = null;
        $bodyText = null;

        try {
            $message->parseBody();
            $bodies = $message->getBodies();
            $bodyHtml = isset($bodies['html']) ? (string) $bodies['html'] : null;
            $bodyText = isset($bodies['text']) ? (string) $bodies['text'] : null;
        } catch (\Throwable $e) {
            Log::debug('InboxSyncService: body fetch failed — ' . $e->getMessage());
        }

        // ── Threading headers (available post-parseBody) ──────────────────
        $inReplyTo  = $this->extractInReplyTo($message);
        $references = $this->extractReferences($message);

        // ── Resolve thread_id and parent_id ───────────────────────────────
        [$threadId, $parentId] = $this->resolveThread($messageId, $inReplyTo, $references);

        // ── Sender type ───────────────────────────────────────────────────
        $user       = User::where('email', $fromEmail)->first();
        $senderType = ($user && $user->isStudent()) ? 'student' : 'external';

        // ── Persist ───────────────────────────────────────────────────────
        $stored = InboxEmail::create([
            'from_email'  => $fromEmail,
            'from_name'   => $fromName,
            'sender_type' => $senderType,
            'user_id'     => $user?->id,
            'subject'     => mb_substr($subject, 0, 255),
            'body_html'   => $bodyHtml,
            'body_text'   => $bodyText,
            'message_id'  => mb_substr($messageId, 0, 255),
            'in_reply_to' => $inReplyTo  ? mb_substr($inReplyTo,  0, 255) : null,
            'references'  => $references ? mb_substr($references, 0, 2000) : null,
            'thread_id'   => $threadId,
            'parent_id'   => $parentId,
            'status'      => 'unread',
            'received_at' => $receivedAt,
        ]);

        // ── Broadcast (fires on any driver; UI polls as fallback) ─────────
        try {
            event(new NewEmailReceived($stored));
        } catch (\Throwable $e) {
            Log::debug('InboxSyncService: broadcast failed (non-fatal) — ' . $e->getMessage());
        }

        return 'imported';
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Thread resolution
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Determine thread_id and parent_id for a new message.
     *
     * Algorithm:
     *  1. Parse the References header into an ordered list of Message-IDs.
     *  2. Walk the list from oldest to newest; stop at the first one that
     *     exists in our inbox_emails table — that gives us the thread root.
     *  3. If none found in References, try In-Reply-To.
     *  4. If still nothing, this message starts a new thread.
     *
     * thread_id is always md5(root_message_id) — stable and collision-resistant.
     * parent_id is the inbox_emails.id of the most-recent known ancestor.
     *
     * @return array{0: string, 1: int|null}  [thread_id, parent_id]
     */
    private function resolveThread(string $messageId, ?string $inReplyTo, ?string $references): array
    {
        // Parse References into array of clean Message-IDs
        $refIds = $this->parseMessageIdList($references ?? '');

        // Add In-Reply-To as the last element (most direct parent)
        if ($inReplyTo) {
            $clean = trim($inReplyTo, '<> ');
            if ($clean && !in_array($clean, $refIds, true)) {
                $refIds[] = $clean;
            }
        }

        if (empty($refIds)) {
            // No threading headers — new standalone thread
            return [md5($messageId), null];
        }

        // Find the oldest ancestor we know about (front of References list)
        $rootThreadId = null;
        $parentId     = null;

        // Walk oldest → newest to find the thread root
        foreach ($refIds as $refMsgId) {
            $existing = InboxEmail::where('message_id', $refMsgId)->first(['id', 'thread_id']);
            if ($existing) {
                // Use the existing thread_id if already set, else derive from its message_id
                $rootThreadId = $existing->thread_id ?? md5($refMsgId);
                break;
            }
        }

        // Find the most-recent known ancestor for parent_id (walk newest → oldest)
        foreach (array_reverse($refIds) as $refMsgId) {
            $existing = InboxEmail::where('message_id', $refMsgId)->first(['id']);
            if ($existing) {
                $parentId = $existing->id;
                break;
            }
        }

        // If we found a thread but no root_thread_id yet (all refs are unknown),
        // derive from the first Reference (oldest in chain)
        if (!$rootThreadId) {
            $rootThreadId = md5($refIds[0]);
        }

        return [$rootThreadId, $parentId];
    }

    /**
     * Parse a space/comma-separated list of RFC 2822 Message-IDs.
     * Strips angle brackets.
     *
     * @return string[]
     */
    private function parseMessageIdList(string $raw): array
    {
        // Split on whitespace or comma, strip <> wrappers
        $ids = preg_split('/[\s,]+/', trim($raw));
        return array_values(array_filter(
            array_map(fn($s) => trim($s, '<> '), $ids),
            fn($s) => $s !== ''
        ));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Header extraction helpers
    // ──────────────────────────────────────────────────────────────────────

    private function extractMessageId(\Webklex\PHPIMAP\Message $message): ?string
    {
        try {
            $mid = $message->getMessageId();
            if ($mid === null) return null;
            $str = $this->safeString($mid);
            return ($str !== '' && $str !== null) ? trim($str, '<> ') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractDate(\Webklex\PHPIMAP\Message $message): \Carbon\Carbon
    {
        try {
            $date = $message->getDate();
            if ($date && $date->count() > 0) {
                $carbonDate = $date->first();
                if ($carbonDate instanceof \Carbon\Carbon) {
                    return $carbonDate;
                }
            }
        } catch (\Throwable $e) { /* fallback */ }
        return now();
    }

    private function extractInReplyTo(\Webklex\PHPIMAP\Message $message): ?string
    {
        try {
            $irt = $message->getInReplyTo();
            if (!$irt) return null;
            $str = $this->safeString($irt);
            return ($str && trim($str) !== '') ? trim($str, '<> ') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractReferences(\Webklex\PHPIMAP\Message $message): ?string
    {
        try {
            // Webklex exposes arbitrary headers via header->get() or direct property
            $refs = null;
            if (method_exists($message, 'getReferences')) {
                $refs = $message->getReferences();
            } else {
                $header = $message->getHeader();
                $refs   = $header?->get('references') ?? null;
            }
            if (!$refs) return null;
            $str = $this->safeString($refs);
            return ($str && trim($str) !== '') ? $str : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeString(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_string($value)) return $value;
        if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
        if (is_array($value)) {
            $first = reset($value);
            return $first !== false ? $this->safeString($first) : null;
        }
        return null;
    }
}

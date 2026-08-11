<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * InboxSyncState
 *
 * Tracks the IMAP sync checkpoint per account.
 * One row per IMAP account (account = 'default' for the configured inbox).
 *
 * Columns used by InboxSyncService:
 *   account          — IMAP account identifier (unique key for lookups)
 *   last_uid         — highest IMAP UID fully and successfully persisted
 *   last_synced_at   — timestamp of the last completed sync run
 *
 * @property int                      $id
 * @property string                   $account
 * @property int|null                 $last_uid
 * @property \Carbon\Carbon|null      $last_synced_at
 * @property \Carbon\Carbon|null      $created_at
 * @property \Carbon\Carbon|null      $updated_at
 */
class InboxSyncState extends Model
{
    protected $table = 'inbox_sync_state';

    protected $fillable = [
        'account',
        'last_uid',
        'last_synced_at',
    ];

    protected $casts = [
        'last_uid'       => 'integer',
        'last_synced_at' => 'datetime',
    ];

    // ── Static helpers used by InboxSyncService ────────────────────────────

    /**
     * Read the current checkpoint UID for the default IMAP account.
     * Returns 0 if no row exists yet (triggers a full initial import).
     */
    public static function readLastUid(string $account = 'default'): int
    {
        $row = static::where('account', $account)->first();
        return ($row && $row->last_uid !== null) ? (int) $row->last_uid : 0;
    }

    /**
     * Advance the checkpoint to the given UID and record the sync timestamp.
     * Uses updateOrCreate so it is safe on both first run and subsequent runs.
     */
    public static function saveLastUid(int $uid, string $account = 'default'): void
    {
        static::updateOrCreate(
            ['account' => $account],
            [
                'last_uid'       => $uid,
                'last_synced_at' => now(),
            ]
        );
    }
}

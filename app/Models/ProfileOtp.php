<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * Represents a one-time password record used to verify password changes.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $code_hash          bcrypt of the 6-digit code
 * @property string      $new_password_hash  bcrypt of the requested new password
 * @property int         $attempts
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProfileOtp extends Model
{
    protected $table = 'profile_otps';

    protected $fillable = [
        'user_id',
        'code_hash',
        'new_password_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'attempts'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes / helpers ───────────────────────────────────────────────────

    /** True if the OTP has not expired and has not been used yet. */
    public function isValid(): bool
    {
        return is_null($this->used_at)
            && $this->expires_at->isFuture()
            && $this->attempts < 5;
    }

    /** Verify the given plaintext code against the stored bcrypt hash. */
    public function checkCode(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code_hash);
    }

    /**
     * Generate a new 6-digit numeric OTP, store its hash, and return the
     * plaintext code (to be included in the email — never stored plain).
     */
    public static function generate(User $user, string $newPasswordHash): array
    {
        // Invalidate any previous unused OTP for this user
        self::where('user_id', $user->id)->whereNull('used_at')->delete();

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = self::create([
            'user_id'           => $user->id,
            'code_hash'         => Hash::make($plainCode),
            'new_password_hash' => $newPasswordHash,
            'attempts'          => 0,
            'expires_at'        => now()->addMinutes(5),
        ]);

        return [$otp, $plainCode];
    }

    /**
     * Find the latest unused, non-expired OTP for a user.
     */
    public static function latestForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }
}

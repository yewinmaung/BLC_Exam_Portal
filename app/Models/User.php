<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\StudentYearRecord;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'is_active', 'force_password_change',
        'phone', 'academic_year', 'exam_session_token', 'last_login_at', 'profile_photo',
        'failed_login_attempts', 'locked_until', 'temporary_password_expires_at',
        'temp_password_last_requested_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'exam_session_token',
    ];

    protected $casts = [
        'email_verified_at'               => 'datetime',
        'last_login_at'                   => 'datetime',
        'is_active'                       => 'boolean',
        'force_password_change'           => 'boolean',
        'academic_year'                   => 'integer',
        'failed_login_attempts'           => 'integer',
        'locked_until'                    => 'datetime',
        'temporary_password_expires_at'   => 'datetime',
        'temp_password_last_requested_at' => 'datetime',
    ];

    public static function academicYears(): array
    {
        return \App\Support\AcademicYear::OPTIONS;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function taughtCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function examsAsTeacher(): HasMany
    {
        return $this->hasMany(Exam::class, 'teacher_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'student_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function studentYearRecords(): HasMany
    {
        return $this->hasMany(StudentYearRecord::class, 'student_id');
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === RoleSlug::ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role?->slug === RoleSlug::TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->role?->slug === RoleSlug::STUDENT;
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    /**
     * Return the full URL to the user's profile photo, or null if none is set.
     * The view falls back to the initials avatar when this returns null.
     */
    public function profilePhotoUrl(): ?string
    {
        if (!$this->profile_photo) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_photo);
    }

    // ── Login Security Helpers ─────────────────────────────────────────────

    /** Maximum failed attempts before the account is locked. */
    public const MAX_FAILED_ATTEMPTS = 3;

    /** How long (minutes) the account is locked after hitting the limit. */
    public const LOCK_DURATION_MINUTES = 10;

    /** How many hours a temporary password is valid. */
    public const TEMP_PASSWORD_EXPIRY_HOURS = 24;

    /**
     * True if the account is currently locked (locked_until is in the future).
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * True if this account has a temporary password that has expired.
     * Only applies when force_password_change = true.
     */
    public function isTemporaryPasswordExpired(): bool
    {
        return $this->force_password_change
            && $this->temporary_password_expires_at !== null
            && $this->temporary_password_expires_at->isPast();
    }

    /**
     * Increment the failed login counter.
     * When the limit is reached, set locked_until.
     */
    public function incrementFailedLogins(): void
    {
        $attempts = ($this->failed_login_attempts ?? 0) + 1;
        $updates  = ['failed_login_attempts' => $attempts];

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $updates['locked_until'] = now()->addMinutes(self::LOCK_DURATION_MINUTES);
        }

        $this->update($updates);
    }

    /**
     * Reset failed login counter and clear any lock.
     * Called on successful authentication.
     */
    public function resetFailedLogins(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    /**
     * Remaining failed attempts before lockout.
     */
    public function remainingLoginAttempts(): int
    {
        $used = $this->failed_login_attempts ?? 0;
        return max(0, self::MAX_FAILED_ATTEMPTS - $used);
    }

    /**
     * Cooldown seconds between temporary password re-requests.
     * Prevents email spam — user must wait 60 s between requests.
     */
    public const TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS = 60;

    /**
     * Returns true if the user may request a new temporary password right now.
     * Conditions (ALL must pass):
     *  - force_password_change = true
     *  - account is NOT currently locked
     *  - at least TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS have elapsed since last request
     */
    public function canRequestNewTempPassword(): bool
    {
        if (!$this->force_password_change) {
            return false;
        }
        if ($this->isLocked()) {
            return false;
        }
        if ($this->temp_password_last_requested_at !== null) {
            $elapsed = (int) $this->temp_password_last_requested_at->diffInSeconds(now());
            if ($elapsed < self::TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS) {
                return false;
            }
        }
        return true;
    }

    /**
     * Seconds remaining in the resend cooldown (0 if cooldown has passed).
     */
    public function tempPasswordCooldownSecondsRemaining(): int
    {
        if ($this->temp_password_last_requested_at === null) {
            return 0;
        }
        $elapsed = (int) $this->temp_password_last_requested_at->diffInSeconds(now());
        return max(0, self::TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS - $elapsed);
    }
}

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
        'name', 'email', 'password', 'role_id', 'is_active', 'phone', 'academic_year',
        'exam_session_token', 'last_login_at', 'profile_photo',
    ];

    protected $hidden = [
        'password', 'remember_token', 'exam_session_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'academic_year' => 'integer',
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
}

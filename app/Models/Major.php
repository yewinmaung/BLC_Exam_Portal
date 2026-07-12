<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Major extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static array $defaults = [
        ['name' => 'Computer Science',   'code' => 'CS'],
        ['name' => 'Computer Technology', 'code' => 'CT'],
    ];

    /** Seed default majors when the table is empty. */
    public static function ensureDefaults(): void
    {
        if (static::exists()) {
            return;
        }

        foreach (static::$defaults as $major) {
            static::firstOrCreate(
                ['code' => $major['code']],
                ['name' => $major['name'], 'is_active' => true]
            );
        }
    }

    public static function resolveIdFromLabel(?string $label): ?int
    {
        if (!$label) {
            return null;
        }

        return static::where('name', $label)
            ->orWhere('code', $label)
            ->value('id');
    }

    public static function codeFromLabel(?string $label): ?string
    {
        if (!$label) {
            return null;
        }

        return static::where('name', $label)
            ->orWhere('code', $label)
            ->value('code') ?? $label;
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YearLevel extends Model
{
    protected $fillable = ['level', 'name', 'department', 'major'];
    protected $casts    = ['level' => 'integer'];

    public static array $names = [
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
        5 => 'Final Year',
    ];

    /** Seed default year levels when the table is empty (e.g. migrations without seeder). */
    public static function ensureDefaults(): void
    {
        if (static::exists()) {
            return;
        }

        foreach (static::$names as $level => $name) {
            static::firstOrCreate(['level' => $level], ['name' => $name]);
        }
    }

    public function studentYearRecords(): HasMany { return $this->hasMany(StudentYearRecord::class); }
}

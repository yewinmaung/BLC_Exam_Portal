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

    public function studentYearRecords(): HasMany { return $this->hasMany(StudentYearRecord::class); }
    public function yearlyExamResults(): HasMany  { return $this->hasMany(YearlyExamResult::class); }
    public function promotionHistoriesFrom(): HasMany { return $this->hasMany(PromotionHistory::class, 'from_year_level_id'); }
    public function promotionHistoriesTo(): HasMany   { return $this->hasMany(PromotionHistory::class, 'to_year_level_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'start_year', 'end_year', 'is_current'];
    protected $casts    = ['is_current' => 'boolean'];

    public function studentYearRecords(): HasMany { return $this->hasMany(StudentYearRecord::class); }
    public function yearlyExamResults(): HasMany  { return $this->hasMany(YearlyExamResult::class); }
    public function certificateLogs(): HasMany    { return $this->hasMany(CertificateLog::class); }
    public function promotionHistories(): HasMany { return $this->hasMany(PromotionHistory::class); }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }
}

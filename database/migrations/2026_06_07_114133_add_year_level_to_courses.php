<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // year_level: 0 = all years, 1-5 = specific year level
            $table->unsignedTinyInteger('year_level')->default(0)->after('is_active')
                  ->comment('0=all years, 1=First, 2=Second, 3=Third, 4=Fourth, 5=Final');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // academic_year_id: null = available in all academic years
            $table->foreignId('academic_year_id')
                  ->nullable()->after('year_level')
                  ->constrained('academic_years')->nullOnDelete()
                  ->comment('null = all academic years');

            // semester: null/0 = both semesters
            $table->unsignedTinyInteger('semester')
                  ->default(0)->after('academic_year_id')
                  ->comment('0=both, 1=sem1, 2=sem2');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'semester']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the ENUM to include fill_blank
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq','true_false','essay','file_upload','document','fill_blank') NOT NULL");

        // Add blank_answer column on answers table for fill_blank correct answers
        Schema::table('answers', function (Blueprint $table) {
            $table->boolean('is_blank_answer')->default(false)->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('is_blank_answer');
        });

        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq','true_false','essay','file_upload','document') NOT NULL");
    }
};

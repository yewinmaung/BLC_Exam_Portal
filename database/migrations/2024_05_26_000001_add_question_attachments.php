<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq','true_false','essay','file_upload','document') NOT NULL");

        Schema::table('questions', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('content_encrypted');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `question_order` to exam_attempts.
 *
 * Stores a JSON array of question IDs in the randomized display order
 * generated once when the student starts the attempt.
 *
 * Example: [5, 2, 8, 1, 3]
 *
 * Rules:
 *  - Set ONCE at attempt creation; never regenerated.
 *  - NULL means no saved order yet (handled gracefully — natural DB order used).
 *  - Never used for grading; only affects display order in the exam view.
 *  - Preserved across page reloads, browser refresh, and session recovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->json('question_order')->nullable()->after('last_question_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('question_order');
        });
    }
};

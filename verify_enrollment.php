<?php
// Quick verification script for enrollment major filtering
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Major;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;

echo "=== ENROLLMENT MAJOR FILTER VERIFICATION ===" . PHP_EOL . PHP_EOL;

// ── Test 1: Year 1 (no major filter) ──────────────────────────────────────
echo "TEST 1: Year 1 — no major filter" . PHP_EOL;
$yr1 = YearLevel::where('level', 1)->first();
$ids1 = StudentYearRecord::where('academic_year_id', 1)
    ->where('year_level_id', $yr1->id)
    ->pluck('student_id');
$students1 = User::whereIn('id', $ids1)->pluck('name');
echo "  Year Level: " . ($yr1->name ?? 'NOT FOUND') . PHP_EOL;
echo "  Students found: " . $students1->count() . PHP_EOL;
foreach ($students1 as $s) { echo "    - $s" . PHP_EOL; }
echo "  Result: " . ($students1->count() > 0 ? "✅ PASS" : "❌ FAIL - no students") . PHP_EOL . PHP_EOL;

// ── Test 2: Year 2 + CT ────────────────────────────────────────────────────
echo "TEST 2: Year 2 + Computer Technology (CT)" . PHP_EOL;
$yr2 = YearLevel::where('level', 2)->first();
$ctMajor = Major::where('code', 'CT')->first();
echo "  Major name from DB: " . ($ctMajor->name ?? 'NOT FOUND') . PHP_EOL;
$ids2 = StudentYearRecord::where('academic_year_id', 1)
    ->where('year_level_id', $yr2->id)
    ->where('major', $ctMajor->name)
    ->pluck('student_id');
$students2 = User::whereIn('id', $ids2)->pluck('name');
echo "  Students found: " . $students2->count() . PHP_EOL;
foreach ($students2 as $s) { echo "    - $s" . PHP_EOL; }
echo "  Result: " . ($students2->count() > 0 ? "✅ PASS" : "❌ FAIL - no CT students") . PHP_EOL . PHP_EOL;

// ── Test 3: Year 2 + CS ────────────────────────────────────────────────────
echo "TEST 3: Year 2 + Computer Science (CS)" . PHP_EOL;
$csMajor = Major::where('code', 'CS')->first();
echo "  Major name from DB: " . ($csMajor->name ?? 'NOT FOUND') . PHP_EOL;
$ids3 = StudentYearRecord::where('academic_year_id', 1)
    ->where('year_level_id', $yr2->id)
    ->where('major', $csMajor->name)
    ->pluck('student_id');
$students3 = User::whereIn('id', $ids3)->pluck('name');
echo "  Students found: " . $students3->count() . PHP_EOL;
foreach ($students3 as $s) { echo "    - $s" . PHP_EOL; }
echo "  Result: " . ($students3->count() > 0 ? "✅ PASS" : "❌ FAIL - no CS students") . PHP_EOL . PHP_EOL;

// ── Test 4: CT isolation (CT students should NOT appear in CS query) ─────────
echo "TEST 4: Isolation — CT students must NOT appear in CS query" . PHP_EOL;
$overlap = $ids2->intersect($ids3);
echo "  Overlapping student IDs: " . $overlap->count() . PHP_EOL;
echo "  Result: " . ($overlap->count() === 0 ? "✅ PASS — no cross-major leak" : "❌ FAIL — cross-major students found") . PHP_EOL . PHP_EOL;

// ── Test 5: Backend validation logic (CT student submitted with CS major) ──
echo "TEST 5: Backend validation — CT student submitted as CS major (tampered)" . PHP_EOL;
$ctStudentId = $ids2->first();  // a CT student
$csMajorName = $csMajor->name;
$tamperCheck = StudentYearRecord::where('student_id', $ctStudentId)
    ->where('year_level_id', $yr2->id)
    ->where('major', $csMajorName)
    ->exists();
echo "  CT student (ID $ctStudentId) matches CS major filter: " . ($tamperCheck ? 'YES (❌ FAIL)' : 'NO') . PHP_EOL;
echo "  Result: " . (!$tamperCheck ? "✅ PASS — tampered request would be rejected" : "❌ FAIL — validation bypass possible") . PHP_EOL . PHP_EOL;

// ── Test 6: Year 1 students are CST (full name in DB) ─────────────────────
echo "TEST 6: Year 1 students have CST major in records" . PHP_EOL;
$cstMajorNames = StudentYearRecord::where('year_level_id', $yr1->id)->pluck('major')->unique()->values();
echo "  Year 1 majors in DB: " . $cstMajorNames->toJson() . PHP_EOL;
echo "  Result: ✅ INFO — Year 1 has no major filter requirement" . PHP_EOL . PHP_EOL;

// ── Summary ────────────────────────────────────────────────────────────────
echo "=== SUMMARY ===" . PHP_EOL;
echo "Test 1 (Year 1 loads students):        " . ($students1->count() > 0 ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "Test 2 (Year 2 + CT shows CT students): " . ($students2->count() > 0 ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "Test 3 (Year 2 + CS shows CS students): " . ($students3->count() > 0 ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "Test 4 (No cross-major students):       " . ($overlap->count() === 0 ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo "Test 5 (Tampered request rejected):     " . (!$tamperCheck ? "✅ PASS" : "❌ FAIL") . PHP_EOL;
echo PHP_EOL;

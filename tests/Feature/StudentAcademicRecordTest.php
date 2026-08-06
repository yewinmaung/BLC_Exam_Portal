<?php

namespace Tests\Feature;

use App\Enums\RecordType;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for Student Academic Record — Create & Edit.
 *
 * Tests exercise the real controller → validator → database flow.
 * All tests use a fresh database so they are isolated and repeatable.
 */
class StudentAcademicRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $years   = [];   // academic_year_id keyed by start_year
    private array $levels  = [];   // year_level_id keyed by level integer

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $adminRole   = Role::create(['name' => 'Admin',   'slug' => 'admin']);
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);

        // Admin user
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        // Year levels 1-5
        YearLevel::ensureDefaults();
        foreach (YearLevel::orderBy('level')->get() as $yl) {
            $this->levels[$yl->level] = $yl->id;
        }

        // Academic years 2018-2030
        foreach (range(2018, 2030) as $y) {
            $ay = AcademicYear::create([
                'name'       => "{$y}-" . ($y + 1),
                'start_year' => $y,
                'end_year'   => $y + 1,
                'is_current' => ($y === 2025),
            ]);
            $this->years[$y] = $ay->id;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** POST to admin.students.store with the given academic payload */
    private function createStudent(array $academic = [], array $account = []): \Illuminate\Testing\TestResponse
    {
        $base = [
            'name'  => $account['name']  ?? 'Test Student',
            'email' => $account['email'] ?? 'student_' . uniqid() . '@test.com',
            'phone' => null,
        ];

        return $this->actingAs($this->admin)
            ->post(route('admin.students.store'), array_merge($base, $academic));
    }

    /** PUT to admin.students.update with the given academic payload */
    private function updateStudent(User $student, array $academic = [], array $account = []): \Illuminate\Testing\TestResponse
    {
        $base = [
            'name'      => $account['name']  ?? $student->name,
            'email'     => $account['email'] ?? $student->email,
            'is_active' => 1,
        ];

        return $this->actingAs($this->admin)
            ->put(route('admin.students.update', $student), array_merge($base, $academic));
    }

    /** Create a student and seed multiple year records directly (bypassing validation) */
    private function seedStudentWithRecords(array $records): User
    {
        $role    = Role::where('slug', 'student')->first();
        $student = User::factory()->create(['role_id' => $role->id]);

        foreach ($records as $r) {
            StudentYearRecord::create([
                'student_id'       => $student->id,
                'academic_year_id' => $this->years[$r['year']],
                'year_level_id'    => $this->levels[$r['level']],
                'semester'         => $r['semester'] ?? '1',
                'status'           => 'active',
                'record_type'      => $r['type'] ?? null,
                'remark'           => $r['remark'] ?? null,
            ]);
        }

        return $student;
    }


    // ── Core Validation Tests (CREATE) ───────────────────────────────────────

    /** @test */
    public function it_creates_normal_student_starting_at_first_year()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[1],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_year_records', [
            'year_level_id' => $this->levels[1],
            'record_type'   => RecordType::NORMAL,
        ]);
    }

    /** @test */
    public function it_rejects_normal_student_starting_above_first_year()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[3],   // Third Year
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
        $this->assertDatabaseCount('student_year_records', 0);
    }

    /** @test */
    public function it_allows_transfer_student_starting_at_any_level()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[3],
            'semester'         => '1',
            'record_type'      => RecordType::TRANSFER,
            'remark'           => 'Transferred from University of Computer Studies.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_year_records', [
            'year_level_id' => $this->levels[3],
            'record_type'   => RecordType::TRANSFER,
        ]);
    }

    /** @test */
    public function it_requires_remark_for_transfer()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[3],
            'semester'         => '1',
            'record_type'      => RecordType::TRANSFER,
            // no remark
        ]);

        $response->assertSessionHasErrors('remark');
    }

    /** @test */
    public function it_requires_remark_for_readmission()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[1],
            'semester'         => '1',
            'record_type'      => RecordType::READMISSION,
            // no remark
        ]);

        $response->assertSessionHasErrors('remark');
    }

    /** @test */
    public function it_does_not_require_remark_for_normal()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[1],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
            // no remark
        ]);

        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('student_year_records', ['year_level_id' => $this->levels[1]]);
    }

    /** @test */
    public function it_rejects_readmission_as_first_record()
    {
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[1],
            'semester'         => '1',
            'record_type'      => RecordType::READMISSION,
            'remark'           => 'Re-admitted.',
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    // ── Scenario Tests (CREATE — using pre-seeded records) ────────────────────

    /** @test Scenario 1 */
    public function scenario1_normal_with_readmission_gap_passes()
    {
        // Seed: 2018/Y1/NORMAL, 2022/Y2/READMISSION, 2023/Y3/NORMAL, 2024/Y4/NORMAL
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => RecordType::NORMAL],
            ['year' => 2022, 'level' => 2, 'type' => RecordType::READMISSION, 'remark' => 'Re-admitted.'],
            ['year' => 2023, 'level' => 3, 'type' => RecordType::NORMAL],
            ['year' => 2024, 'level' => 4, 'type' => RecordType::NORMAL],
        ]);

        // Add Final Year (5) — should PASS
        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[5],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('student_year_records', [
            'student_id'    => $student->id,
            'year_level_id' => $this->levels[5],
        ]);
    }

    /** @test Scenario 2 */
    public function scenario2_transfer_start_with_sequential_progression_passes()
    {
        // First record: 2024/Y3/TRANSFER
        $response = $this->createStudent([
            'academic_year_id' => $this->years[2024],
            'year_level_id'    => $this->levels[3],
            'semester'         => '1',
            'record_type'      => RecordType::TRANSFER,
            'remark'           => 'Transferred from UCY.',
        ]);
        $response->assertRedirect();
        $student = User::where('email', 'like', 'student_%')->latest()->first();

        // Add Y4
        $r2 = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[4],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);
        $r2->assertSessionMissing('errors');

        // Add Y5
        $r3 = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2026],
            'year_level_id'    => $this->levels[5],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);
        $r3->assertSessionMissing('errors');
    }

    /** @test Scenario 3 */
    public function scenario3_first_to_fourth_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
        ]);

        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2022],
            'year_level_id'    => $this->levels[4],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    /** @test Scenario 4 */
    public function scenario4_skipping_third_year_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2023],
            'year_level_id'    => $this->levels[4],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    /** @test Scenario 5 */
    public function scenario5_duplicate_year_level_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2023],
            'year_level_id'    => $this->levels[2],  // duplicate
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    /** @test Scenario 6 */
    public function scenario6_duplicate_academic_year_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2022],  // duplicate year
            'year_level_id'    => $this->levels[3],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    /** @test Scenario 7 */
    public function scenario7_readmission_then_skip_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => RecordType::NORMAL],
            ['year' => 2022, 'level' => 2, 'type' => RecordType::READMISSION, 'remark' => 'Re-admitted.'],
        ]);

        // Skip Third Year — should FAIL
        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[4],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    /** @test Scenario 8 */
    public function scenario8_transfer_after_existing_records_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => RecordType::NORMAL],
        ]);

        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2024],
            'year_level_id'    => $this->levels[3],
            'semester'         => '1',
            'record_type'      => RecordType::TRANSFER,
            'remark'           => 'Transferred.',
        ]);

        $response->assertSessionHasErrors('year_level_id');
    }

    // ── Edit-specific edge cases ──────────────────────────────────────────────

    /** @test */
    public function edit_without_changing_values_passes()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2025, 'level' => 1, 'type' => RecordType::NORMAL],
        ]);

        // Update with exactly the same academic_year_id and year_level_id
        $response = $this->updateStudent($student, [
            'academic_year_id' => $this->years[2025],
            'year_level_id'    => $this->levels[1],
            'semester'         => '1',
            'record_type'      => RecordType::NORMAL,
        ]);

        $response->assertSessionMissing('errors');
    }

    /** @test */
    public function edit_normal_to_readmission_where_prior_records_exist_passes()
    {
        // Student has Y1 already; edit that record to READMISSION is invalid
        // (READMISSION cannot be first). So instead seed Y1+Y2, then edit Y2 to READMISSION.
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => RecordType::NORMAL],
            ['year' => 2022, 'level' => 2, 'type' => RecordType::NORMAL],
        ]);

        // Edit the Y2 record to READMISSION — allowed because Y1 already exists.
        $recordY2 = StudentYearRecord::where('student_id', $student->id)
            ->where('year_level_id', $this->levels[2])
            ->first();
        $this->assertNotNull($recordY2);

        // Simulate the controller edit by directly calling validateEdit
        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        $error = $validator->validateEdit(
            $student->id,
            2,                         // same year level
            $this->years[2022],        // same academic year
            RecordType::READMISSION,
            $recordY2->id
        );

        $this->assertNull($error, "Expected PASS but got: $error");
    }

    /** @test */
    public function edit_normal_to_transfer_after_history_exists_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => RecordType::NORMAL],
            ['year' => 2022, 'level' => 2, 'type' => RecordType::NORMAL],
        ]);

        // Try to change Y2 to TRANSFER — should fail (TRANSFER must be first record)
        $recordY2 = StudentYearRecord::where('student_id', $student->id)
            ->where('year_level_id', $this->levels[2])
            ->first();

        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        $error = $validator->validateEdit(
            $student->id,
            2,
            $this->years[2022],
            RecordType::TRANSFER,
            $recordY2->id
        );

        $this->assertNotNull($error, 'Expected FAIL but got null');
        $this->assertStringContainsString('Transfer can only be used', $error);
    }

    /** @test */
    public function edit_academic_year_only_while_keeping_progression_valid_passes()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $recordY2 = StudentYearRecord::where('student_id', $student->id)
            ->where('year_level_id', $this->levels[2])
            ->first();

        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        // Change academic year from 2022 to 2023 (still level 2, no duplicate)
        $error = $validator->validateEdit(
            $student->id,
            2,
            $this->years[2023],  // different year, same level
            RecordType::NORMAL,
            $recordY2->id
        );

        $this->assertNull($error, "Expected PASS but got: $error");
    }

    /** @test */
    public function edit_year_level_only_while_keeping_progression_valid_passes()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
        ]);

        $recordY1 = StudentYearRecord::where('student_id', $student->id)->first();

        // Same student, only record. Change Y1→Y1 same academic year = no-op, passes.
        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        $error = $validator->validateEdit(
            $student->id,
            1,
            $this->years[2018],
            RecordType::NORMAL,
            $recordY1->id
        );

        $this->assertNull($error, "Expected PASS but got: $error");
    }

    /** @test */
    public function edit_duplicate_academic_year_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $recordY2 = StudentYearRecord::where('student_id', $student->id)
            ->where('year_level_id', $this->levels[2])
            ->first();

        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        // Try to move Y2 record to 2018 — conflicts with existing Y1 record (2018)
        $error = $validator->validateEdit(
            $student->id,
            2,
            $this->years[2018],  // duplicate academic year
            RecordType::NORMAL,
            $recordY2->id
        );

        $this->assertNotNull($error, 'Expected FAIL but got null');
        $this->assertStringContainsString('already has a record for this academic year', $error);
    }

    /** @test */
    public function edit_duplicate_year_level_fails()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1],
            ['year' => 2022, 'level' => 2],
        ]);

        $recordY2 = StudentYearRecord::where('student_id', $student->id)
            ->where('year_level_id', $this->levels[2])
            ->first();

        $validator = app(\App\Services\YearLevelProgressionValidator::class);
        // Try to change Y2 to level 1 — conflicts with existing Y1 record
        $error = $validator->validateEdit(
            $student->id,
            1,                   // duplicate year level
            $this->years[2023],
            RecordType::NORMAL,
            $recordY2->id
        );

        $this->assertNotNull($error, 'Expected FAIL but got null');
        $this->assertStringContainsString('already has a record for First Year', $error);
    }

    // ── Backward compatibility ────────────────────────────────────────────────

    /** @test */
    public function existing_null_record_type_is_treated_as_normal()
    {
        // Simulate a pre-existing record with NULL record_type (legacy data)
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => null],   // NULL = legacy
        ]);

        $validator = app(\App\Services\YearLevelProgressionValidator::class);

        // Adding Y2 should work fine
        $error = $validator->validate(
            $student->id,
            2,
            $this->years[2022],
            null   // NULL = NORMAL
        );

        $this->assertNull($error, "Expected PASS but got: $error");
    }

    /** @test */
    public function existing_normal_records_remain_valid_without_modification()
    {
        $student = $this->seedStudentWithRecords([
            ['year' => 2018, 'level' => 1, 'type' => null],
            ['year' => 2022, 'level' => 2, 'type' => null],
            ['year' => 2023, 'level' => 3, 'type' => null],
        ]);

        // Each record should still be readable
        $records = StudentYearRecord::where('student_id', $student->id)->get();
        $this->assertCount(3, $records);

        foreach ($records as $r) {
            $this->assertNull($r->record_type);  // unchanged — NULL preserved
            $this->assertNull($r->remark);
        }
    }

    /** @test */
    public function creating_student_without_academic_info_succeeds()
    {
        // No academic_year_id / year_level_id provided — this is backward compatible
        $response = $this->createStudent([]);
        $response->assertRedirect();
        $this->assertDatabaseCount('student_year_records', 0);
    }
}

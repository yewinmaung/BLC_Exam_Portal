<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ReAttemptRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReAttemptVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $otherStudent;
    private Exam $exam;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        // Create students
        $this->student = User::factory()->create([
            'role_id' => 3, // Student role
        ]);

        $this->otherStudent = User::factory()->create([
            'role_id' => 3, // Student role
        ]);

        // Create course
        $this->course = Course::factory()->create([
            'status' => 'published',
        ]);

        // Enroll both students
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        Enrollment::create([
            'student_id' => $this->otherStudent->id,
            'course_id' => $this->course->id,
        ]);

        // Create exam (not published)
        $this->exam = Exam::factory()->create([
            'course_id' => $this->course->id,
            'status' => 'approved', // Not published
        ]);
    }

    /** @test */
    public function student_cannot_see_unpublished_exam_without_approved_reattempt()
    {
        $response = $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam));

        $response->assertStatus(404);
    }

    /** @test */
    public function student_can_see_exam_with_approved_reattempt()
    {
        // Create approved re-attempt
        ReAttemptRequest::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'status' => 'approved',
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam));

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_see_other_students_approved_reattempt()
    {
        // Create approved re-attempt for student 1
        ReAttemptRequest::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'status' => 'approved',
            'reason' => 'Test reason',
        ]);

        // Student 2 tries to access
        $response = $this->actingAs($this->otherStudent)
            ->get(route('student.exams.show', $this->exam));

        $response->assertStatus(404);
    }

    /** @test */
    public function approved_reattempt_appears_in_exam_list()
    {
        // Create approved re-attempt
        ReAttemptRequest::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'status' => 'approved',
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.index'));

        $response->assertStatus(200);
        // Exam should appear in the response (even though not published)
        $response->assertSee($this->exam->title);
    }

    /** @test */
    public function pending_reattempt_does_not_grant_access()
    {
        // Create pending (not approved) re-attempt
        ReAttemptRequest::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'status' => 'pending', // Not approved
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam));

        $response->assertStatus(404);
    }

    /** @test */
    public function rejected_reattempt_does_not_grant_access()
    {
        // Create rejected re-attempt
        ReAttemptRequest::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'status' => 'rejected',
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam));

        $response->assertStatus(404);
    }

    /** @test */
    public function student_cannot_start_exam_without_approved_reattempt()
    {
        $response = $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam));

        $response->assertStatus(404);
    }

    /** @test */
    public function published_exams_still_work_normally()
    {
        // Update exam to published
        $this->exam->update(['status' => 'published']);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam));

        // Should work without re-attempt
        $response->assertStatus(200);
    }
}

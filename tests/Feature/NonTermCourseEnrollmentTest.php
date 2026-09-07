<?php

namespace Tests\Feature;

use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Institution;
use App\Models\Student;
use App\Support\Enrollments\EnrollmentCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NonTermCourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_date_course_can_complete_without_an_enrollment_term(): void
    {
        $institution = Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Kairos Seminary',
            'slug' => 'kairos-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);
        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'KAI-101',
            'title' => 'Kairos Formation',
            'slug' => 'kairos-formation',
            'scheduling_basis' => Course::SCHEDULING_BASIS_COMPLETION_DATE,
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Kairos',
            'last_name' => 'Student',
            'email' => 'kairos-'.Str::lower(Str::random(8)).'@example.test',
            'student_number' => 'K'.random_int(100000, 999999),
            'status' => 'active',
        ]);
        $enrollment = CourseEnrollment::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => null,
            'status' => 'enrolled',
            'enrolled_at' => '2026-01-01 09:00:00',
        ]);

        app(EnrollmentCompletionService::class)->complete($enrollment, [
            'final_grade' => 'A',
            'credits_attempted' => '3.00',
            'credits_earned' => '3.00',
            'grade_points' => '4.00',
            'completed_at' => '2026-09-15',
            'notes' => 'Completed through the non-term pathway.',
        ], [
            'progress_basis_raw' => null,
            'progress_status_raw' => null,
            'evidence_summary_raw' => 'Manual completion.',
            'requires_override' => false,
        ]);

        $enrollment->refresh();
        $this->assertNull($enrollment->academic_term_id);
        $this->assertSame('completed', $enrollment->status);
        $this->assertModelExists($enrollment->academicRecord);
        $this->assertNull($enrollment->academicRecord->academic_term_id);
        $this->assertSame('2026-09-15', $enrollment->academicRecord->completed_at->toDateString());
        $this->assertSame(1, AcademicRecord::query()->where('course_enrollment_id', $enrollment->id)->count());
    }
}

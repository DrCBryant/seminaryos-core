<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FacultyArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_is_institution_scoped_and_status_vocabulary_is_centralized(): void
    {
        $institution = $this->createInstitution();
        $faculty = $this->createFaculty($institution);

        $this->assertSame($institution->id, $faculty->institution_id);
        $this->assertSame('Rev. Test Faculty', $faculty->full_name);
        $this->assertSame(Faculty::STATUS_OPTIONS, Faculty::statusOptions());
    }

    public function test_completion_date_teaching_assignment_can_remain_without_a_term_and_history_survives_inactivation(): void
    {
        $institution = $this->createInstitution();
        $faculty = $this->createFaculty($institution);
        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'KAI-201',
            'title' => 'Kairos Formation',
            'slug' => 'kairos-formation',
            'scheduling_basis' => Course::SCHEDULING_BASIS_COMPLETION_DATE,
            'status' => 'active',
        ]);
        $assignment = TeachingAssignment::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'faculty_id' => $faculty->id,
            'course_id' => $course->id,
            'academic_term_id' => null,
            'role' => 'mentor',
            'status' => 'assigned',
        ]);

        $faculty->update(['status' => 'inactive']);

        $this->assertNull($assignment->fresh()->academic_term_id);
        $this->assertSame('inactive', $faculty->fresh()->status);
        $this->assertModelExists($assignment);
        $this->assertSame(TeachingAssignment::ROLE_OPTIONS, TeachingAssignment::roleOptions());
        $this->assertSame(TeachingAssignment::STATUS_OPTIONS, TeachingAssignment::statusOptions());
    }

    protected function createInstitution(): Institution
    {
        return Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Faculty Seminary '.Str::random(5),
            'slug' => 'faculty-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);
    }

    protected function createFaculty(Institution $institution): Faculty
    {
        return Faculty::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Rev. Test',
            'last_name' => 'Faculty',
            'email' => 'faculty-'.Str::lower(Str::random(8)).'@example.test',
            'status' => 'active',
        ]);
    }
}

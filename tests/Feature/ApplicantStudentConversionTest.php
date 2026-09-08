<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Support\Admissions\ApplicantStudentConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicantStudentConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_applicant_converts_once_and_preserves_admissions_context(): void
    {
        [$institution, $program] = $this->context();
        $applicant = $this->applicant($institution, $program, ['status' => 'accepted']);

        $result = app(ApplicantStudentConversionService::class)->convert($applicant);

        $this->assertFalse($result['already_converted']);
        $this->assertDatabaseHas('students', [
            'id' => $result['student']->id,
            'institution_id' => $institution->id,
            'program_id' => $program->id,
            'applicant_id' => $applicant->id,
            'email' => 'applicant@example.test',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'enrolled',
        ]);
        $this->assertNotNull($applicant->fresh()->converted_at);
    }

    public function test_conversion_is_idempotent(): void
    {
        [$institution, $program] = $this->context();
        $applicant = $this->applicant($institution, $program, ['status' => 'accepted']);
        $service = app(ApplicantStudentConversionService::class);

        $first = $service->convert($applicant);
        $second = $service->convert($applicant->fresh());

        $this->assertTrue($second['already_converted']);
        $this->assertSame($first['student']->id, $second['student']->id);
        $this->assertSame(1, Student::query()->where('applicant_id', $applicant->id)->count());
    }

    public function test_only_accepted_applicants_can_convert(): void
    {
        [$institution, $program] = $this->context();
        $applicant = $this->applicant($institution, $program, ['status' => 'applied']);

        $this->expectException(ValidationException::class);
        app(ApplicantStudentConversionService::class)->convert($applicant);
    }

    public function test_duplicate_student_email_requires_registrar_review(): void
    {
        [$institution, $program] = $this->context();
        Student::create([
            'institution_id' => $institution->id,
            'program_id' => $program->id,
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'email' => 'applicant@example.test',
            'student_number' => 'S-EXISTING',
            'status' => 'active',
        ]);
        $applicant = $this->applicant($institution, $program, ['status' => 'accepted']);

        $this->expectException(ValidationException::class);
        app(ApplicantStudentConversionService::class)->convert($applicant);

        $this->assertDatabaseMissing('students', ['applicant_id' => $applicant->id]);
        $this->assertSame('accepted', $applicant->fresh()->status);
    }

    public function test_status_options_are_centralized(): void
    {
        $this->assertSame('Accepted', Applicant::statusOptions()['accepted']);
        $this->assertSame('Enrolled', Applicant::statusOptions()['enrolled']);
    }

    private function context(): array
    {
        $institution = Institution::create([
            'name' => 'Test Seminary',
            'slug' => 'test-seminary',
            'type' => 'seminary',
        ]);
        $program = Program::create([
            'institution_id' => $institution->id,
            'code' => 'MDIV',
            'title' => 'Master of Divinity',
            'slug' => 'mdiv',
        ]);

        return [$institution, $program];
    }

    private function applicant(Institution $institution, Program $program, array $attributes = []): Applicant
    {
        return Applicant::create(array_merge([
            'institution_id' => $institution->id,
            'program_id' => $program->id,
            'first_name' => 'Ada',
            'last_name' => 'Applicant',
            'email' => 'applicant@example.test',
            'status' => 'inquiry',
        ], $attributes));
    }
}

<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseOfferingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_report_an_out_of_term_warning_when_offering_dates_are_within_term_boundaries(): void
    {
        $context = $this->createCourseOfferingContext();

        $warning = CourseOffering::academicTermBoundaryWarning(
            $context['academicTerm'],
            '2026-08-20',
            '2026-12-10',
        );

        $this->assertNull($warning);
    }

    public function test_it_reports_when_the_offering_start_date_is_before_the_term_start_date(): void
    {
        $context = $this->createCourseOfferingContext();

        $warning = CourseOffering::academicTermBoundaryWarning(
            $context['academicTerm'],
            '2026-08-10',
            '2026-12-10',
        );

        $this->assertNotNull($warning);
        $this->assertSame(['The offering start date is before the term start date.'], $warning['outside_boundaries']);
        $this->assertStringContainsString('Academic Term dates 2026-08-15 to 2026-12-15', $warning['message']);
        $this->assertStringContainsString('Course Offering dates 2026-08-10 to 2026-12-10', $warning['message']);
        $this->assertStringContainsString('Registrar-approved exceptions are permitted. Saving is not blocked.', $warning['message']);
    }

    public function test_it_reports_when_the_offering_end_date_is_after_the_term_end_date(): void
    {
        $context = $this->createCourseOfferingContext();

        $warning = CourseOffering::academicTermBoundaryWarning(
            $context['academicTerm'],
            '2026-08-20',
            '2026-12-20',
        );

        $this->assertNotNull($warning);
        $this->assertSame(['The offering end date is after the term end date.'], $warning['outside_boundaries']);
    }

    public function test_it_reports_when_both_offering_date_boundaries_extend_outside_the_term(): void
    {
        $context = $this->createCourseOfferingContext();

        $warning = CourseOffering::academicTermBoundaryWarning(
            $context['academicTerm'],
            '2026-08-10',
            '2026-12-20',
        );

        $this->assertNotNull($warning);
        $this->assertSame([
            'The offering start date is before the term start date.',
            'The offering end date is after the term end date.',
        ], $warning['outside_boundaries']);
    }

    public function test_it_allows_persisting_an_offering_outside_term_boundaries(): void
    {
        $context = $this->createCourseOfferingContext();

        $courseOffering = CourseOffering::query()->create([
            'institution_id' => $context['institution']->id,
            'uuid' => (string) Str::uuid(),
            'course_id' => $context['course']->id,
            'academic_term_id' => $context['academicTerm']->id,
            'section_code' => 'EXT1',
            'delivery_mode' => 'online',
            'start_date' => '2026-08-10',
            'end_date' => '2026-12-20',
            'status' => 'planned',
            'progress_basis' => CourseOffering::PROGRESS_BASIS_ATTENDANCE,
        ]);

        $this->assertDatabaseHas('course_offerings', [
            'id' => $courseOffering->id,
            'start_date' => '2026-08-10 00:00:00',
            'end_date' => '2026-12-20 00:00:00',
        ]);
    }

    /**
     * @return array{institution: Institution, course: Course, academicTerm: AcademicTerm}
     */
    protected function createCourseOfferingContext(): array
    {
        $institution = Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Seminary '.Str::random(6),
            'slug' => 'test-seminary-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);

        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'BIB-'.random_int(100, 999),
            'title' => 'Biblical Studies '.Str::random(4),
            'slug' => 'biblical-studies-'.Str::lower(Str::random(8)),
            'credit_hours' => '3.00',
            'status' => 'active',
            'is_public' => false,
        ]);

        $academicTerm = AcademicTerm::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Fall 2026',
            'code' => 'FALL-2026',
            'academic_year' => '2026-2027',
            'term_type' => 'fall',
            'start_date' => '2026-08-15',
            'end_date' => '2026-12-15',
            'status' => 'active',
        ]);

        return compact('institution', 'course', 'academicTerm');
    }
}

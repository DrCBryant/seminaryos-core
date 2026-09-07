<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Institution;
use App\Support\AcademicTerms\ReportingTermResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingTermResolverTest extends TestCase
{
    use CreatesReportingTermFixtures;
    use RefreshDatabase;

    public function test_it_resolves_inclusive_standard_term_boundaries(): void
    {
        $institution = $this->createInstitution();
        $this->createTerm($institution, 'Fall', 'fall', '2026-08-01', '2026-12-31');

        $result = app(ReportingTermResolver::class)->resolve($this->completionDateCourse(), $institution->id, now()->setDate(2026, 8, 1));
        $this->assertSame(ReportingTermResolver::RESOLVED, $result['status']);

        $result = app(ReportingTermResolver::class)->resolve($this->completionDateCourse(), $institution->id, now()->setDate(2026, 12, 31));
        $this->assertSame(ReportingTermResolver::RESOLVED, $result['status']);
    }

    public function test_it_ignores_specialized_terms(): void
    {
        $institution = $this->createInstitution();
        $this->createTerm($institution, 'Module', 'module', '2026-09-01', '2026-09-30');

        $result = app(ReportingTermResolver::class)->resolve($this->completionDateCourse(), $institution->id, now()->setDate(2026, 9, 15));
        $this->assertSame(ReportingTermResolver::NO_MATCH, $result['status']);
    }

    public function test_it_reports_ambiguity_for_overlapping_standard_terms(): void
    {
        $institution = $this->createInstitution();
        $this->createTerm($institution, 'Fall', 'fall', '2026-08-01', '2026-12-31');
        $this->createTerm($institution, 'Intake', 'spring', '2026-12-01', '2027-03-31');

        $result = app(ReportingTermResolver::class)->resolve($this->completionDateCourse(), $institution->id, now()->setDate(2026, 12, 15));
        $this->assertSame(ReportingTermResolver::AMBIGUOUS, $result['status']);
        $this->assertCount(2, $result['candidates']);
    }

    public function test_it_reports_no_match_without_inventing_a_term(): void
    {
        $institution = $this->createInstitution();
        $result = app(ReportingTermResolver::class)->resolve($this->completionDateCourse(), $institution->id, now()->setDate(2026, 12, 15));
        $this->assertSame(ReportingTermResolver::NO_MATCH, $result['status']);
        $this->assertNull($result['term']);
    }

    protected function completionDateCourse(): Course
    {
        return new Course(['scheduling_basis' => Course::SCHEDULING_BASIS_COMPLETION_DATE]);
    }
}

trait CreatesReportingTermFixtures
{
    protected function createInstitution(): Institution
    {
        return Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Resolver Seminary '.Str::random(5),
            'slug' => 'resolver-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);
    }

    protected function createTerm(Institution $institution, string $name, string $type, string $start, string $end): AcademicTerm
    {
        return AcademicTerm::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'code' => strtoupper($type).'-'.Str::random(4),
            'academic_year' => '2026-2027',
            'term_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
        ]);
    }
}

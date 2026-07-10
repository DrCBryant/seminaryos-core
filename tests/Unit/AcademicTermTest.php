<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_a_reusable_display_label(): void
    {
        $institution = $this->createInstitution();

        $term = AcademicTerm::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Fall Semester',
            'code' => 'FALL-2026',
            'academic_year' => '2026-2027',
            'term_type' => 'fall',
            'start_date' => '2026-08-15',
            'end_date' => '2026-12-15',
            'status' => 'active',
        ]);

        $this->assertSame('Fall Semester (2026-2027)', $term->display_label);
    }

    public function test_it_exposes_documented_term_type_options(): void
    {
        $this->assertSame([
            'fall' => 'Fall',
            'spring' => 'Spring',
            'summer' => 'Summer',
            'winter' => 'Winter',
            'intensive' => 'Intensive',
            'module' => 'Module',
            'custom' => 'Custom',
        ], AcademicTerm::termTypeOptions());
    }

    public function test_it_exposes_documented_status_options(): void
    {
        $this->assertSame([
            'draft' => 'Draft',
            'open' => 'Open',
            'active' => 'Active',
            'completed' => 'Completed',
            'archived' => 'Archived',
        ], AcademicTerm::statusOptions());
    }

    public function test_it_orders_terms_for_selection_by_academic_year_desc_then_start_date_asc(): void
    {
        $institution = $this->createInstitution();

        $older = $this->createAcademicTerm($institution, 'Fall 2025', '2025-2026', '2025-08-20');
        $laterInNewestYear = $this->createAcademicTerm($institution, 'Spring 2026', '2026-2027', '2027-01-10');
        $earlierInNewestYear = $this->createAcademicTerm($institution, 'Fall 2026', '2026-2027', '2026-08-15');

        $orderedIds = AcademicTerm::query()
            ->orderedForSelection()
            ->pluck('id')
            ->all();

        $this->assertSame([
            $earlierInNewestYear->id,
            $laterInNewestYear->id,
            $older->id,
        ], $orderedIds);
    }

    protected function createInstitution(): Institution
    {
        return Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Seminary',
            'slug' => 'test-seminary-'.Str::lower(Str::random(6)),
            'type' => 'seminary',
            'status' => 'active',
        ]);
    }

    protected function createAcademicTerm(Institution $institution, string $name, string $academicYear, string $startDate): AcademicTerm
    {
        return AcademicTerm::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '-')).'-'.Str::replace('-', '', $academicYear),
            'academic_year' => $academicYear,
            'term_type' => 'fall',
            'start_date' => $startDate,
            'end_date' => Carbon::parse($startDate)->addMonths(4)->toDateString(),
            'status' => 'active',
        ]);
    }
}

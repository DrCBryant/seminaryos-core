<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('registrationWindows')]
    public function test_registration_windows(?string $start, ?string $end, string $date, ?bool $open, bool $before, bool $after): void
    {
        $term = new AcademicTerm([
            'registration_start_date' => $start,
            'registration_end_date' => $end,
            'status' => 'draft',
        ]);
        $on = Carbon::parse($date);

        $this->assertSame($start !== null || $end !== null, $term->hasRegistrationWindow());
        $this->assertSame($open, $term->isRegistrationOpenOn($on));
        $this->assertSame($before, $term->isBeforeRegistrationWindow($on));
        $this->assertSame($after, $term->isAfterRegistrationWindow($on));
        $this->assertSame('draft', $term->status);
        $this->assertSame($date, $on->format('Y-m-d H:i:s'));
        $this->assertSame(match (true) {
            $open === null => 'No automated registration window configured',
            $before => 'Ordinary registration has not opened',
            $after => 'Ordinary registration window has closed',
            default => 'Ordinary registration is open',
        }, $term->registrationWindowLabel($on));
    }

    public static function registrationWindows(): array
    {
        return [
            'unconfigured' => [null, null, '2026-07-10 12:00:00', null, false, false],
            'before' => ['2026-07-01', '2026-07-31', '2026-06-30 23:59:59', false, true, false],
            'inside' => ['2026-07-01', '2026-07-31', '2026-07-10 12:00:00', true, false, false],
            'after' => ['2026-07-01', '2026-07-31', '2026-08-01 00:00:00', false, false, true],
            'opening' => ['2026-07-01', '2026-07-31', '2026-07-01 00:00:00', true, false, false],
            'closing' => ['2026-07-01', '2026-07-31', '2026-07-31 23:59:59', true, false, false],
            'start only before' => ['2026-07-01', null, '2026-06-30 12:00:00', false, true, false],
            'start only open' => ['2026-07-01', null, '2027-07-01 12:00:00', true, false, false],
            'end only open' => [null, '2026-07-31', '2025-07-31 12:00:00', true, false, false],
            'end only after' => [null, '2026-07-31', '2026-08-01 12:00:00', false, false, true],
            'single day' => ['2026-07-01', '2026-07-01', '2026-07-01 23:59:59', true, false, false],
        ];
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

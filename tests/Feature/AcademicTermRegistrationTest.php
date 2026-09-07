<?php

namespace Tests\Feature;

use App\Filament\Resources\AcademicTerms\Pages\CreateAcademicTerm;
use App\Filament\Resources\AcademicTerms\Pages\ListAcademicTerms;
use App\Filament\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Pages\EditCourseEnrollment;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicTermRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institution = Institution::query()->create([
            'name' => 'Test Seminary', 'slug' => 'test-seminary', 'type' => 'seminary', 'status' => 'active',
        ]);
        $user = User::factory()->create(['current_institution_id' => $this->institution->id]);
        $user->institutions()->attach($this->institution->id, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(now()->setDate(2026, 8, 15)->startOfDay());
    }

    protected function termData(): array
    {
        return [
            'institution_id' => $this->institution->id,
            'name' => 'Fall', 'code' => 'FALL-2026', 'academic_year' => '2026-2027',
            'term_type' => 'fall', 'status' => 'active',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31',
        ];
    }

    #[DataProvider('validRegistrationDates')]
    public function test_term_form_accepts_optional_windows(?string $start, ?string $end): void
    {
        Livewire::test(CreateAcademicTerm::class)
            ->fillForm($this->termData() + ['registration_start_date' => $start, 'registration_end_date' => $end])
            ->call('create')->assertHasNoFormErrors();
        $term = AcademicTerm::query()->sole();
        $this->assertSame($start, $term->registration_start_date?->toDateString());
        $this->assertSame($end, $term->registration_end_date?->toDateString());
        $this->assertSame('active', $term->status);
    }

    public static function validRegistrationDates(): array
    {
        return [[null, null], ['2026-06-01', null], [null, '2026-07-31'], ['2026-06-01', '2026-06-01']];
    }

    public function test_term_form_rejects_reversed_boundaries(): void
    {
        Livewire::test(CreateAcademicTerm::class)
            ->fillForm(array_replace($this->termData(), [
                'end_date' => '2026-07-31',
                'registration_start_date' => '2026-07-01', 'registration_end_date' => '2026-06-30',
            ]))->call('create')->assertHasFormErrors([
                'end_date' => 'after_or_equal', 'registration_end_date' => 'after_or_equal',
            ]);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    public function test_overlapping_active_terms_remain_allowed_and_visible(): void
    {
        $first = AcademicTerm::query()->create($this->termData());
        Livewire::test(CreateAcademicTerm::class)
            ->fillForm(array_replace($this->termData(), ['name' => 'Module', 'code' => 'MOD', 'term_type' => 'module']))
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('academic_terms', 2);
        Livewire::test(ListAcademicTerms::class)->assertCanSeeTableRecords(AcademicTerm::all())
            ->assertTableColumnStateSet('registration_window', 'No automated registration window configured', $first);
    }

    public static function outsideRegistrationDates(): array
    {
        return [
            ['2026-08-15', 'Ordinary registration window has closed'],
            ['2026-05-15', 'Ordinary registration has not opened'],
        ];
    }

    #[DataProvider('outsideRegistrationDates')]
    public function test_registrar_can_create_and_edit_enrollment_outside_registration(string $date, string $notice): void
    {
        $term = AcademicTerm::query()->create($this->termData() + [
            'registration_start_date' => '2026-06-01', 'registration_end_date' => '2026-07-31',
        ]);
        $course = Course::query()->create([
            'institution_id' => $this->institution->id, 'code' => 'BIB101', 'title' => 'Bible', 'slug' => 'bible',
        ]);
        $student = Student::query()->create([
            'institution_id' => $this->institution->id, 'first_name' => 'Test', 'last_name' => 'Student',
            'email' => 'student@example.test', 'student_number' => 'S001',
        ]);
        $this->travelTo(now()->setDateFrom($date));
        $this->assertFalse($term->isRegistrationOpenOn(today()));
        Livewire::test(CreateCourseEnrollment::class)->fillForm([
            'institution_id' => $this->institution->id, 'student_id' => $student->id,
            'course_id' => $course->id, 'academic_term_id' => $term->id,
            'status' => 'enrolled', 'enrolled_at' => $date.' 09:00:00',
        ])->assertSee($notice)
            ->assertSee('saving is not blocked')->call('create')->assertHasNoFormErrors();
        $enrollment = CourseEnrollment::query()->sole();
        Livewire::test(EditCourseEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->fillForm(['notes' => 'Registrar update outside window'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Registrar update outside window', $enrollment->fresh()->notes);
        $this->assertNull($enrollment->course_offering_id);
    }
}

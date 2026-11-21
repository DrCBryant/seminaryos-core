<?php

namespace Tests\Feature;

use App\Filament\Resources\AcademicRecords\Pages\CreateAcademicRecord;
use App\Filament\Resources\AcademicRecords\Pages\EditAcademicRecord;
use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Filament\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Pages\EditCourseEnrollment;
use App\Filament\Resources\CourseOfferings\Pages\EditCourseOffering;
use App\Filament\Resources\CourseOfferings\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\Student;
use App\Models\User;
use App\Support\Enrollments\EnrollmentCompletionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicHistoryProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Student $student;

    private Course $course;

    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create(['name' => 'Seminary', 'slug' => 'seminary', 'type' => 'seminary', 'status' => 'active']);
        $this->student = $this->student('Ada');
        $this->course = Course::create(['institution_id' => $this->institution->id, 'code' => 'BIB101', 'title' => 'Bible', 'slug' => 'bible']);
        $this->term = AcademicTerm::create([
            'institution_id' => $this->institution->id, 'name' => 'Fall', 'code' => 'F26', 'academic_year' => '2026-2027',
            'term_type' => 'fall', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31',
        ]);
        $user = User::factory()->create(['current_institution_id' => $this->institution->id]);
        $user->institutions()->attach($this->institution, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_direct_enrollment_create_and_edit_require_guided_completion(): void
    {
        Livewire::test(CreateCourseEnrollment::class)
            ->fillForm(['status' => 'completed'] + $this->enrollmentData())
            ->call('create')->assertHasFormErrors(['status']);
        $this->assertDatabaseCount('course_enrollments', 0);

        Livewire::test(CreateCourseEnrollment::class)
            ->fillForm($this->enrollmentData())
            ->call('create')->assertHasNoFormErrors();
        $enrollment = CourseEnrollment::query()->sole();

        Livewire::test(EditCourseEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->fillForm(['status' => 'completed'])->call('save')->assertHasFormErrors(['status']);

        Livewire::test(EditCourseEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->fillForm(['status' => 'withdrawn', 'notes' => 'Registrar note'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('withdrawn', $enrollment->fresh()->status);
        $this->assertSame('Registrar note', $enrollment->fresh()->notes);
        $this->assertNull($enrollment->fresh()->completed_at);
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_section_enrollment_actions_cannot_fabricate_completion(): void
    {
        $offering = CourseOffering::create([
            'institution_id' => $this->institution->id, 'course_id' => $this->course->id,
            'academic_term_id' => $this->term->id, 'section_code' => 'A', 'delivery_mode' => 'online', 'status' => 'in_progress',
        ]);
        $manager = Livewire::test(CourseEnrollmentsRelationManager::class, [
            'ownerRecord' => $offering, 'pageClass' => EditCourseOffering::class,
        ]);

        $manager->callTableAction('create', data: ['student_id' => $this->student->id, 'status' => 'completed'])
            ->assertHasTableActionErrors(['status'])
            ->unmountAction();
        $this->assertDatabaseCount('course_enrollments', 0);

        $manager->callTableAction('create', data: ['student_id' => $this->student->id, 'status' => 'enrolled'])
            ->assertHasNoTableActionErrors();
        $enrollment = CourseEnrollment::query()->sole();

        $manager->callTableAction('edit', $enrollment, ['status' => 'completed'])
            ->assertHasTableActionErrors(['status']);
        $this->assertSame($offering->id, $enrollment->course_offering_id);
        $this->assertSame('enrolled', $enrollment->fresh()->status);
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_finalized_enrollment_preserves_outcome_when_an_open_edit_saves(): void
    {
        $enrollment = CourseEnrollment::create($this->enrollmentData());
        $edit = Livewire::test(EditCourseEnrollment::class, ['record' => $enrollment->getRouteKey()]);
        $this->complete($enrollment);

        $edit->fillForm(['status' => 'withdrawn', 'final_grade' => 'F', 'completed_at' => null, 'notes' => 'Reviewed'])
            ->call('save')->assertHasNoFormErrors();

        $enrollment->refresh();
        $this->assertSame('Reviewed', $enrollment->notes);
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame('A', $enrollment->final_grade);
        $this->assertSame('2026-10-01', $enrollment->completed_at->toDateString());
        $this->assertSame('A', $enrollment->academicRecord->final_grade);
        $this->assertFalse($enrollment->forceDelete());
    }

    public function test_historical_records_remain_enterable_and_notes_are_editable(): void
    {
        $fields = $this->enrollmentData();
        unset($fields['status']);

        Livewire::test(CreateAcademicRecord::class)->fillForm($fields + [
            'course_code' => 'OLD101', 'course_title' => 'Historical Bible', 'status' => 'transfer',
            'final_grade' => 'P', 'completed_at' => '2026-08-10',
        ])->call('create')->assertHasNoFormErrors();

        $record = AcademicRecord::query()->sole();
        $this->assertNull($record->course_enrollment_id);

        Livewire::test(EditAcademicRecord::class, ['record' => $record->getRouteKey()])
            ->assertActionDoesNotExist('delete')
            ->fillForm(['final_grade' => 'F', 'status' => 'failed', 'notes' => 'Source verified'])
            ->call('save')->assertHasNoFormErrors();
        Livewire::test(ListAcademicRecords::class)->assertTableBulkActionDoesNotExist('delete');

        $this->assertSame('P', $record->fresh()->final_grade);
        $this->assertSame('transfer', $record->fresh()->status);
        $this->assertSame('Source verified', $record->fresh()->notes);
        $this->assertFalse($record->delete());
        $this->assertFalse($record->forceDelete());
    }

    public function test_student_actions_preserve_outcomes_and_historical_transcripts(): void
    {
        $this->complete(CourseEnrollment::create($this->enrollmentData()));
        $issuedStudent = $this->student('Grace');
        $transcript = OfficialTranscript::create([
            'institution_id' => $this->institution->id, 'student_id' => $issuedStudent->id,
            'transcript_number' => 'OT-1', 'status' => 'issued', 'issued_at' => '2026-10-02',
        ]);

        Livewire::test(EditStudent::class, ['record' => $this->student->getRouteKey()])->callAction('delete');

        DB::table('students')->where('id', $issuedStudent->id)->update(['deleted_at' => now()]);
        Livewire::test(ListStudents::class)->filterTable('trashed', true)
            ->callTableBulkAction('forceDelete', [$issuedStudent]);

        $this->assertNotSoftDeleted($this->student);
        $this->assertModelExists($issuedStudent);
        $this->assertModelExists($transcript);
        $this->assertDatabaseCount('academic_records', 1);
        $this->assertFalse($this->course->forceDelete());
        $this->assertFalse($this->institution->forceDelete());

        $unprotected = $this->student('New');
        $this->assertTrue($unprotected->delete());
        $this->assertTrue($unprotected->forceDelete());
    }

    public function test_current_institution_cannot_create_enrollment_in_another_institution(): void
    {
        $other = Institution::create(['name' => 'Other', 'slug' => 'other', 'type' => 'seminary', 'status' => 'active']);

        Livewire::test(CreateCourseEnrollment::class)
            ->fillForm(['institution_id' => $other->id] + $this->enrollmentData())
            ->call('create')->assertHasFormErrors(['institution_id']);

        $this->assertDatabaseCount('course_enrollments', 0);
    }

    private function enrollmentData(): array
    {
        return [
            'institution_id' => $this->institution->id, 'student_id' => $this->student->id,
            'course_id' => $this->course->id, 'academic_term_id' => $this->term->id, 'status' => 'enrolled',
        ];
    }

    private function student(string $name): Student
    {
        return Student::create([
            'institution_id' => $this->institution->id, 'first_name' => $name, 'last_name' => 'Student',
            'email' => strtolower($name).'@example.test', 'student_number' => $name,
        ]);
    }

    private function complete(CourseEnrollment $enrollment): void
    {
        app(EnrollmentCompletionService::class)->complete($enrollment, [
            'final_grade' => 'A', 'credits_attempted' => 3, 'credits_earned' => 3, 'completed_at' => '2026-10-01',
        ], ['progress_basis_raw' => null, 'progress_status_raw' => null, 'evidence_summary_raw' => null, 'requires_override' => false]);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\DegreeConferral;
use App\Models\Institution;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\Student;
use App\Models\StudentRequirementEvidence;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DegreeConferralTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Student $student;

    private Program $program;

    private Course $course;

    private ProgramRequirement $requirement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create(['name' => 'Conferral Seminary', 'slug' => 'conferral', 'type' => 'seminary']);
        $this->course = Course::create(['institution_id' => $this->institution->id, 'code' => 'B101', 'title' => 'Bible', 'slug' => 'bible']);
        $this->program = $this->program('MDIV', 'Master of Divinity', 'M.Div.');
        $this->student = Student::create([
            'institution_id' => $this->institution->id, 'program_id' => $this->program->id,
            'first_name' => 'Ada', 'last_name' => 'Student', 'email' => 'ada@example.test',
            'student_number' => 'D1', 'status' => 'active',
        ]);
        $this->requirement = $this->requirement($this->program);
        $this->signIn();
    }

    public function test_eligible_student_receives_a_durable_award_and_keeps_existing_academic_status(): void
    {
        $record = $this->passingRecord();
        $audit = $this->editor()->mountAction('viewDegreeAudit');
        $this->assertStringContainsString('Bible requirement', $this->modalContent($audit));

        $conferral = $this->editor()->mountAction('conferDegree');
        $this->assertStringContainsString('All evaluated requirements are complete', $this->modalContent($conferral));
        $conferral->setActionData(['conferred_on' => '2026-11-01', 'registrar_remarks' => 'Approved in registrar review.'])
            ->callMountedAction()->assertHasNoActionErrors();

        $award = DegreeConferral::query()->sole();
        $this->assertSame($this->institution->id, $award->institution_id);
        $this->assertSame($this->student->id, $award->student_id);
        $this->assertSame($this->program->id, $award->program_id);
        $this->assertSame(auth()->id(), $award->conferred_by_user_id);
        $this->assertSame('2026-11-01', $award->conferred_on->toDateString());
        $this->assertNotNull($award->decided_at);
        $this->assertSame('Approved in registrar review.', $award->registrar_remarks);
        $this->assertSame($record->id, $award->eligibility_snapshot['groups'][0]['requirements'][0]['records'][0]['id']);
        $this->assertSame('active', $this->student->fresh()->status);
        $this->assertModelExists($record);

        $this->program->update(['title' => 'Revised Catalog Title', 'code' => 'NEW', 'credential_type' => 'Revised']);
        $this->requirement->update(['name' => 'Revised curriculum']);
        $this->assertSame('MDIV', $award->fresh()->program_code);
        $this->assertSame('M.Div.', $award->fresh()->credential_type);
        $this->assertSame($award->eligibility_snapshot, $award->fresh()->eligibility_snapshot);
        $history = $this->editor()->mountAction('viewAwardedCredentials');
        $this->assertStringContainsString('Master of Divinity', $this->modalContent($history));
        $this->assertStringNotContainsString('Revised Catalog Title', $this->modalContent($history));
    }

    public function test_ineligible_or_unverifiable_curriculum_cannot_create_an_award(): void
    {
        $this->student->update(['status' => 'graduated']);
        $conferral = $this->editor()->mountAction('conferDegree');
        $this->assertStringContainsString('Eligibility cannot be verified', $this->modalContent($conferral));
        $conferral->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $this->assertDatabaseCount('degree_conferrals', 0);
        $this->assertDatabaseCount('academic_records', 0);
        $this->assertSame('graduated', $this->student->fresh()->status);

        $this->passingRecord();
        $this->requirement->update(['minimum_grade' => 'A']);
        $conferral = $this->editor()->mountAction('conferDegree');
        $this->assertStringContainsString('cannot verify a configured requirement type or minimum grade', $this->modalContent($conferral));
        $conferral->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $this->assertDatabaseCount('degree_conferrals', 0);
        $this->assertDatabaseCount('academic_records', 1);
    }

    public function test_previously_open_requests_recheck_eligibility_and_cannot_duplicate_a_conferral(): void
    {
        $this->passingRecord();
        $stale = $this->editor()->mountAction('conferDegree');
        $this->requirement->update(['minimum_grade_points' => 4]);
        $stale->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $this->assertDatabaseCount('degree_conferrals', 0);

        $this->requirement->update(['minimum_grade_points' => null]);
        $first = $this->editor()->mountAction('conferDegree');
        $second = $this->editor()->mountAction('conferDegree');
        $first->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $second->setActionData(['conferred_on' => '2026-11-02'])->callMountedAction();

        $award = DegreeConferral::query()->sole();
        $this->assertSame('2026-11-01', $award->conferred_on->toDateString());
        $duplicate = $award->getAttributes();
        unset($duplicate['id']);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('degree_conferrals')->insert($duplicate);
    }

    public function test_student_can_receive_awards_for_two_programs(): void
    {
        $this->passingRecord();
        $this->editor()->callAction('conferDegree', ['conferred_on' => '2026-11-01']);

        $second = $this->program('MA', 'Master of Arts', 'M.A.');
        $this->requirement($second);
        $this->student->update(['program_id' => $second->id]);
        $this->editor()->callAction('conferDegree', ['conferred_on' => '2026-11-02']);

        $this->assertSame([$this->program->id, $second->id], $this->student->degreeConferrals()->orderBy('id')->pluck('program_id')->all());
        $this->assertSame('active', $this->student->fresh()->status);
    }

    public function test_staff_revoked_memberships_and_cross_institution_programs_cannot_confer(): void
    {
        $this->passingRecord();
        $opened = $this->editor()->mountAction('conferDegree');
        DB::table('institution_user')->where('user_id', auth()->id())->update(['status' => 'suspended']);
        $opened->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $this->assertDatabaseCount('degree_conferrals', 0);

        $this->signIn('staff');
        $this->editor()->assertActionHidden('conferDegree');
        $this->assertDatabaseCount('degree_conferrals', 0);

        $other = Institution::create(['name' => 'Other Seminary', 'slug' => 'other-conferral', 'type' => 'seminary']);
        $foreign = Program::create([
            'institution_id' => $other->id, 'code' => 'MA', 'title' => 'Foreign', 'slug' => 'ma', 'credential_type' => 'MA',
        ]);
        $this->student->update(['program_id' => $foreign->id]);
        $this->signIn();

        $conferral = $this->editor()->mountAction('conferDegree');
        $this->assertStringContainsString('Assign an institutional program', $this->modalContent($conferral));
        $conferral->setActionData(['conferred_on' => '2026-11-01'])->callMountedAction();
        $this->assertDatabaseCount('degree_conferrals', 0);
    }

    public function test_only_recognized_evidence_satisfies_the_requirement_and_is_captured(): void
    {
        $this->requirement->update(['course_id' => null, 'requirement_type' => 'non_course_requirement']);
        $evidence = StudentRequirementEvidence::create([
            'institution_id' => $this->institution->id,
            'student_id' => $this->student->id,
            'program_id' => $this->program->id,
            'program_requirement_id' => $this->requirement->id,
            'status' => 'pending',
            'evidence_title' => 'Registrar-approved portfolio waiver',
        ]);

        $this->editor()->callAction('conferDegree', ['conferred_on' => '2026-11-01']);
        $this->assertDatabaseCount('degree_conferrals', 0);

        $evidence->update(['status' => 'waived', 'approved_at' => '2026-10-01', 'approved_by_user_id' => auth()->id()]);
        $this->editor()->callAction('conferDegree', ['conferred_on' => '2026-11-01']);
        $award = DegreeConferral::query()->sole();
        $this->assertSame($evidence->id, $award->eligibility_snapshot['groups'][0]['requirements'][0]['evidence']['id']);
        $this->assertSame('waived', $award->eligibility_snapshot['groups'][0]['requirements'][0]['evidence']['status']);

        $evidence->update(['status' => 'archived']);
        $this->assertSame('waived', $award->fresh()->eligibility_snapshot['groups'][0]['requirements'][0]['evidence']['status']);
    }

    public function test_award_cannot_be_changed_or_deleted_through_the_database(): void
    {
        $this->passingRecord();
        $this->editor()->callAction('conferDegree', ['conferred_on' => '2026-11-01']);
        $award = DegreeConferral::query()->sole();

        foreach ([
            fn () => DB::table('degree_conferrals')->where('id', $award->id)->update(['program_title' => 'Changed']),
            fn () => DB::table('degree_conferrals')->where('id', $award->id)->delete(),
        ] as $write) {
            try {
                $write();
                $this->fail('Expected the database to reject the write.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('permanent institutional record', $exception->getMessage());
            }
        }

        $this->assertFalse($this->student->delete());
        $this->assertSame('Master of Divinity', $award->fresh()->program_title);
    }

    private function modalContent($component): string
    {
        return $component->instance()->getMountedAction()->getModalContent()->toHtml();
    }

    private function editor()
    {
        return Livewire::test(EditStudent::class, ['record' => $this->student->getRouteKey()]);
    }

    private function signIn(string $role = 'admin'): void
    {
        $actor = User::factory()->create(['current_institution_id' => $this->institution->id, 'status' => 'active']);
        $actor->institutions()->attach($this->institution, ['role' => $role, 'status' => 'active']);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function program(string $code, string $title, string $credential): Program
    {
        return Program::create([
            'institution_id' => $this->institution->id, 'code' => $code, 'title' => $title,
            'slug' => strtolower($code), 'credential_type' => $credential,
        ]);
    }

    private function requirement(Program $program): ProgramRequirement
    {
        $context = ['institution_id' => $this->institution->id, 'program_id' => $program->id];
        $group = ProgramRequirementGroup::create($context + ['name' => 'Core', 'group_type' => 'core', 'is_active' => true]);

        return ProgramRequirement::create($context + [
            'program_requirement_group_id' => $group->id, 'course_id' => $this->course->id,
            'name' => 'Bible requirement', 'requirement_type' => 'specific_course', 'is_active' => true,
        ]);
    }

    private function passingRecord(): AcademicRecord
    {
        return AcademicRecord::create([
            'institution_id' => $this->institution->id, 'student_id' => $this->student->id,
            'course_id' => $this->course->id, 'course_code' => 'B101', 'course_title' => 'Bible',
            'status' => 'completed', 'final_grade' => 'A', 'credits_attempted' => 3, 'credits_earned' => 3,
            'earns_credit' => true, 'is_passing' => true, 'completed_at' => '2026-10-01',
        ]);
    }
}

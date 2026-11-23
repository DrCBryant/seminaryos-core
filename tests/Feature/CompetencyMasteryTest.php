<?php

namespace Tests\Feature;

use App\Filament\Faculty\Pages\FacultyPortal;
use App\Filament\Resources\ProgramRequirementGroups\Pages\EditProgramRequirementGroup;
use App\Filament\Resources\ProgramRequirementGroups\RelationManagers\ProgramRequirementsRelationManager;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Support\DegreeAuditPreview;
use App\Models\AcademicTerm;
use App\Models\CompetencyMasteryDecision;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\DegreeConferral;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\MasterAssessment;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\User;
use App\Support\Assessments\FinalizeCompetencyMastery;
use App\Support\Portals\AcademicIdentityAccess;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CompetencyMasteryTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Program $program;

    private ProgramRequirementGroup $group;

    private Course $course;

    private CourseOffering $section;

    private Student $student;

    private Faculty $faculty;

    private User $admin;

    private User $mentor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create(['name' => 'Seminary', 'slug' => 'seminary', 'type' => 'seminary']);
        $tenant = ['institution_id' => $this->institution->id];
        $this->program = Program::create($tenant + ['code' => 'CERT', 'title' => 'Certificate', 'slug' => 'cert', 'credential_type' => 'Certificate']);
        $this->group = ProgramRequirementGroup::create($tenant + [
            'program_id' => $this->program->id, 'name' => 'Core', 'group_type' => 'core', 'is_active' => true,
        ]);
        $this->course = Course::create($tenant + ['code' => 'BIB', 'title' => 'Supporting course', 'slug' => 'bib']);
        $term = AcademicTerm::create($tenant + [
            'code' => 'SPR', 'name' => 'Spring', 'academic_year' => '2026', 'term_type' => 'spring',
            'start_date' => '2026-01-01', 'end_date' => '2026-06-01',
        ]);
        $this->section = CourseOffering::create($tenant + [
            'course_id' => $this->course->id, 'academic_term_id' => $term->id, 'section_code' => 'S1',
            'delivery_mode' => 'online', 'status' => 'in_progress', 'progress_basis' => CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT,
        ]);
        $this->student = Student::create($tenant + [
            'program_id' => $this->program->id, 'first_name' => 'Maya', 'last_name' => 'Reed',
            'student_number' => 'M1', 'email' => 'maya@example.test', 'status' => 'active',
        ]);
        $this->faculty = Faculty::create($tenant + ['first_name' => 'Avery', 'last_name' => 'Mentor', 'email' => 'avery@example.test']);
        $this->admin = $this->account('admin');
        $this->mentor = $this->account('faculty');
        $membership = $this->mentor->institutions()->firstOrFail()->pivot;
        AcademicIdentityAccess::link($this->admin, $this->faculty, $membership->id);
        $this->faculty->mentees()->attach($this->student);
    }

    public function test_registrar_can_map_and_unmap_explicitly_while_staff_and_course_requirements_cannot(): void
    {
        $competency = $this->requirement('Interpretation');
        $traditional = $this->requirement('Traditional course', 'specific_course');
        $assessment = $this->assessment();
        $other = Institution::create(['name' => 'Other', 'slug' => 'other', 'type' => 'seminary']);
        $foreign = $this->assessment((int) $other->id);
        $this->actingAs($this->account('staff'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->manager()->assertDontSee('Map Master Assessment');

        $this->actingAs($this->admin);
        $action = TestAction::make('mapAssessment')->table($competency);
        $this->manager()->assertActionHidden(TestAction::make('mapAssessment')->table($traditional))
            ->callAction($action, ['assessment_id' => $foreign->id])->assertHasActionErrors(['assessment_id']);
        $this->manager()->callAction($action, ['assessment_id' => $assessment->id])->assertHasNoActionErrors();
        $this->assertTrue($competency->masterAssessments()->whereKey($assessment->id)->exists());
        $attempt = $this->attempt($assessment, StudentMasterAssessmentAttempt::STATUS_PASSED);
        $this->manager()->callAction(TestAction::make('unmapAssessment')->table($competency), ['assessment_id' => $assessment->id])
            ->assertHasNoActionErrors();
        $this->assertFalse($competency->masterAssessments()->exists());
        $this->assertModelExists($attempt);
    }

    public function test_database_rejects_cross_institution_mappings_non_competencies_and_mapped_type_changes(): void
    {
        $competency = $this->requirement('Competency');
        $traditional = $this->requirement('Traditional', 'specific_course');
        $assessment = $this->assessment();
        $other = Institution::create(['name' => 'Other', 'slug' => 'other', 'type' => 'seminary']);
        $foreign = $this->assessment((int) $other->id);
        $mapping = [
            'institution_id' => $this->institution->id,
            'program_requirement_id' => $traditional->id,
            'master_assessment_id' => $assessment->id,
        ];
        $this->assertRejected(fn () => DB::table('competency_assessment_mappings')->insert($mapping));
        $this->assertRejected(fn () => DB::table('competency_assessment_mappings')->insert($mapping + ['requirement_type' => 'specific_course']));
        $this->assertRejected(fn () => $this->map($competency, $foreign));
        $this->map($competency, $assessment);
        $this->assertRejected(fn () => $competency->update(['requirement_type' => 'specific_course']));
    }

    public function test_only_mentor_finalization_establishes_mapped_mastery_and_registrar_can_confer_from_frozen_evidence(): void
    {
        $first = $this->requirement('Interpretation');
        $second = $this->requirement('Synthesis');
        $unmapped = $this->requirement('Unmapped competency');
        $assessment = $this->assessment();
        $this->map($first, $assessment);
        $this->map($second, $assessment);
        $attempt = $this->attempt($assessment, StudentMasterAssessmentAttempt::STATUS_PASSED);
        $enrollment = CourseEnrollment::create([
            'institution_id' => $this->institution->id, 'student_id' => $this->student->id, 'course_id' => $this->course->id,
            'course_offering_id' => $this->section->id, 'status' => 'enrolled',
        ]);

        $this->assertSame('satisfied', SectionProgressEvaluator::evaluateEnrollment($enrollment)['progress_status']);
        $this->assertCount(3, DegreeAuditPreview::getViewData($this->student)['notEvaluatedRequirements']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditStudent::class, ['record' => $this->student->id])
            ->callAction('conferDegree', ['conferred_on' => '2026-11-01']);
        $this->assertDatabaseCount('degree_conferrals', 0);

        $this->actingAs($this->mentor);
        $this->get('/faculty?section=mentees&selection='.$this->student->id)->assertOk()
            ->assertSee('Mapped assessments')->assertSee('Finalize mastery')->assertDontSee('Private evidence');
        Filament::setCurrentPanel(Filament::getPanel('faculty'));
        Livewire::withQueryParams(['section' => 'mentees', 'selection' => $this->student->id])->test(FacultyPortal::class)
            ->callAction('finalizeMastery', arguments: ['attempt' => $attempt->id])->assertHasNoActionErrors();

        $decision = CompetencyMasteryDecision::query()->sole();
        $this->assertSame($this->faculty->id, $decision->mentor_faculty_id);
        $this->assertSame($this->mentor->id, $decision->decided_by_user_id);
        $this->assertNotNull($decision->decided_at);
        $this->assertSame('Private evidence', $decision->snapshot['attempt']['assessor_notes']);
        $this->assertSame([$first->id, $second->id], array_column($decision->snapshot['requirements'], 'id'));
        $this->assertSame($decision->id, $this->finalize($attempt)->id);

        $first->masterAssessments()->detach($assessment);
        $assessment->update(['title' => 'Renamed assessment']);
        $attempt->update(['assessor_notes' => 'Edited notes']);
        $this->assertRejected(fn () => DB::table('competency_mastery_decisions')->where('id', $decision->id)->update(['snapshot' => '{}']));
        $this->assertRejected(fn () => DB::table('competency_mastery_decisions')->where('id', $decision->id)->delete());
        $this->assertRejected(fn () => $attempt->delete());
        $this->assertRejected(fn () => $assessment->delete());
        $this->assertModelExists($attempt);
        $this->assertSame('Private evidence', $decision->fresh()->snapshot['attempt']['assessor_notes']);

        $audit = DegreeAuditPreview::getViewData($this->student->fresh());
        $this->assertCount(2, $audit['completedRequirements']);
        $this->assertSame($unmapped->id, $audit['notEvaluatedRequirements']->sole()['requirement']->id);
        $unmapped->update(['is_active' => false]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditStudent::class, ['record' => $this->student->id])
            ->callAction('conferDegree', ['conferred_on' => '2026-11-01'])->assertHasNoActionErrors();
        $award = DegreeConferral::query()->sole();
        $frozen = $award->eligibility_snapshot['groups'][0]['requirements'][0]['mastery'];
        $this->assertSame($decision->id, $frozen['id']);
        $this->assertSame('Master Assessment', $frozen['snapshot']['assessment']['title']);
        $this->assertSame('satisfied', SectionProgressEvaluator::evaluateEnrollment($enrollment->fresh())['progress_status']);
    }

    public function test_unqualified_attempts_and_unrelated_accounts_are_rejected(): void
    {
        $first = $this->requirement('Mapped');
        $assessment = $this->assessment();
        $this->map($first, $assessment);
        $attempt = $this->attempt($assessment, StudentMasterAssessmentAttempt::STATUS_SUBMITTED);

        $this->actingAs($this->mentor);
        foreach ([StudentMasterAssessmentAttempt::STATUS_NOT_STARTED, StudentMasterAssessmentAttempt::STATUS_SUBMITTED,
            StudentMasterAssessmentAttempt::STATUS_FAILED, StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED] as $status) {
            $attempt->update(['status' => $status]);
            $this->assertInvalidFinalization($attempt);
        }
        $attempt->update(['status' => StudentMasterAssessmentAttempt::STATUS_PASSED]);
        $assessment->update(['status' => MasterAssessment::STATUS_DRAFT]);
        $this->assertInvalidFinalization($attempt);
        $assessment->update(['status' => MasterAssessment::STATUS_ACTIVE]);
        $this->assertInvalidFinalization($this->attempt($this->assessment(), StudentMasterAssessmentAttempt::STATUS_PASSED));

        $this->actingAs($this->admin);
        try {
            $this->finalize($attempt, $this->admin);
            $this->fail('Registrar access alone must not authorize mastery.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->actingAs($this->mentor);
        $this->faculty->mentees()->detach($this->student);
        $this->get('/faculty?section=mentees&selection='.$this->student->id)->assertNotFound();
        $this->expectException(HttpException::class);
        $this->finalize($attempt);
    }

    public function test_attempts_cannot_be_finalized_in_another_institution_or_program(): void
    {
        $requirement = $this->requirement('Mapped competency');
        $assessment = $this->assessment();
        $this->map($requirement, $assessment);
        $attempt = $this->attempt($assessment, StudentMasterAssessmentAttempt::STATUS_PASSED);
        $program = Program::create([
            'institution_id' => $this->institution->id, 'code' => 'OTHER', 'title' => 'Another program', 'slug' => 'another',
        ]);
        $this->student->update(['program_id' => $program->id]);
        $this->actingAs($this->mentor);
        $this->get('/faculty?section=mentees&selection='.$this->student->id)->assertOk()
            ->assertDontSee('Mapped competency')->assertDontSee('Finalize mastery');
        $this->assertInvalidFinalization($attempt);
        $this->student->update(['program_id' => $this->program->id]);

        $outside = Institution::create(['name' => 'Outside', 'slug' => 'outside', 'type' => 'seminary']);
        $this->mentor->institutions()->attach($outside, ['role' => 'faculty', 'status' => 'active']);
        $this->mentor->update(['current_institution_id' => $outside->id]);
        $this->get('/faculty?section=mentees&selection='.$this->student->id)->assertForbidden();
        $this->expectException(ModelNotFoundException::class);
        $this->finalize($attempt);
    }

    private function account(string $role): User
    {
        $user = User::factory()->create(['current_institution_id' => $this->institution->id]);
        $user->institutions()->attach($this->institution, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function requirement(string $name, string $type = ProgramRequirement::TYPE_COMPETENCY): ProgramRequirement
    {
        return $this->group->programRequirements()->create([
            'institution_id' => $this->institution->id, 'program_id' => $this->program->id,
            'course_id' => $this->course->id, 'name' => $name, 'requirement_type' => $type,
        ]);
    }

    private function assessment(?int $institutionId = null): MasterAssessment
    {
        return MasterAssessment::create([
            'institution_id' => $institutionId ?? $this->institution->id, 'course_offering_id' => $this->section->id,
            'title' => 'Master Assessment', 'status' => MasterAssessment::STATUS_ACTIVE, 'rubric' => 'Recorded rubric',
        ]);
    }

    private function attempt(MasterAssessment $assessment, string $status): StudentMasterAssessmentAttempt
    {
        return $this->student->studentMasterAssessmentAttempts()->create([
            'institution_id' => $this->institution->id, 'course_offering_id' => $this->section->id,
            'master_assessment_id' => $assessment->id, 'status' => $status, 'assessor_notes' => 'Private evidence',
        ]);
    }

    private function map(ProgramRequirement $requirement, MasterAssessment $assessment): void
    {
        $requirement->masterAssessments()->attach($assessment, ['institution_id' => $this->institution->id]);
    }

    private function finalize(StudentMasterAssessmentAttempt $attempt, ?User $actor = null): CompetencyMasteryDecision
    {
        return app(FinalizeCompetencyMastery::class)->finalize($actor ?? $this->mentor, $this->student->id, $attempt->id);
    }

    private function assertInvalidFinalization(StudentMasterAssessmentAttempt $attempt): void
    {
        try {
            $this->finalize($attempt);
            $this->fail('Unqualified attempt was finalized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attempt', $exception->errors());
        }
        $this->assertDatabaseCount('competency_mastery_decisions', 0);
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Database accepted an invalid mutation.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->errorInfo);
        }
    }

    private function manager()
    {
        return Livewire::test(ProgramRequirementsRelationManager::class, [
            'ownerRecord' => $this->group, 'pageClass' => EditProgramRequirementGroup::class,
        ]);
    }
}

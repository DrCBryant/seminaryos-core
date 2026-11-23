<?php

namespace Tests\Feature;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\DegreeConferral;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\Student;
use App\Models\StudentRequirementEvidence;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_requires_a_link_and_active_account_institution_membership_and_context(): void
    {
        $home = $this->campus('Home');

        $this->get('/student')->assertRedirect(route('filament.student.auth.login'));
        $this->actingAs($home['admin'])->get('/student')->assertForbidden();
        $unlinked = $this->account($home['institution'], 'student');
        $this->actingAs($unlinked)->get('/student')->assertForbidden();

        $linked = $home['account'];
        $response = $this->actingAs($linked)->get('/student')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $linked->update(['status' => 'suspended']);
        $this->get('/student?section=academic-record')->assertForbidden();
        $linked->update(['status' => 'active']);
        $linked->institutions()->updateExistingPivot($home['institution']->id, ['status' => 'inactive']);
        $this->get('/student?section=courses')->assertForbidden();
        $linked->institutions()->updateExistingPivot($home['institution']->id, ['status' => 'active']);
        $linked->update(['current_institution_id' => null]);
        $this->get('/student?section=awards')->assertForbidden();
        $linked->update(['current_institution_id' => $home['institution']->id]);
        $home['institution']->update(['status' => 'inactive']);
        $this->get('/student?section=degree-progress')->assertForbidden();
    }

    public function test_shared_session_supports_dual_identity_and_only_authorized_registrars_see_the_return_link(): void
    {
        $home = $this->campus('Home');
        $facultyOnly = $this->account($home['institution'], 'faculty');
        $faculty = Faculty::create([
            'institution_id' => $home['institution']->id, 'first_name' => 'Faculty', 'last_name' => 'Member', 'email' => 'faculty@example.test',
        ]);
        AcademicIdentityAccess::link($home['admin'], $faculty, $this->membership($facultyOnly, $home['institution']));
        $this->actingAs($facultyOnly)->get('/student')->assertForbidden();
        AcademicIdentityAccess::link($home['admin'], $faculty, $this->membership($home['account'], $home['institution']));

        $this->actingAs($home['account'])->get('/student')->assertOk()
            ->assertSee('Home Learner')->assertDontSee('Registrar workspace');
        $this->get('/admin/students')->assertForbidden();

        AcademicIdentityAccess::link($home['admin'], $home['student'], $this->membership($home['admin'], $home['institution']));
        $this->actingAs($home['admin'])->get('/student')->assertOk()->assertSee('Registrar workspace');
        $this->get('/admin/students')->assertOk();
        $this->assertAuthenticatedAs($home['admin']);
    }

    public function test_linked_student_can_sign_in_with_existing_filament_authentication(): void
    {
        $home = $this->campus('Home');
        Filament::setCurrentPanel(Filament::getPanel('student'));
        Livewire::test(Login::class)->fillForm(['email' => $home['account']->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/student');
        $this->assertAuthenticatedAs($home['account']);
        $this->get('/student')->assertOk()->assertSee('Home Learner');
    }

    public function test_portal_resolves_the_owned_identity_and_ignores_supplied_record_identifiers(): void
    {
        $home = $this->campus('Home');
        $abroad = $this->campus('Abroad');
        $peer = $this->student($home['institution'], 'Peer');
        $this->record($home, $peer, ['course_title' => 'Other student outcome']);
        $this->record($home, $home['student'], ['course_title' => 'Home recorded outcome']);
        $this->record($abroad, $abroad['student'], ['course_title' => 'Abroad recorded outcome']);
        $home['account']->institutions()->attach($abroad['institution'], ['role' => 'student']);
        AcademicIdentityAccess::link($abroad['admin'], $abroad['student'], $this->membership($home['account'], $abroad['institution']));

        $this->actingAs($home['account'])
            ->get('/student?section=academic-record&student_id='.$peer->id.'&institution_id='.$abroad['institution']->id)
            ->assertOk()->assertSee('Home recorded outcome')->assertDontSee('Other student outcome')->assertDontSee('Abroad recorded outcome');
        $this->get('/student/'.$peer->id)->assertNotFound();
        $this->get('/student?section=registrar')->assertNotFound();
        $this->get('/student?section[]=courses')->assertNotFound();

        $home['account']->update(['current_institution_id' => $abroad['institution']->id]);
        $this->get('/student?section=academic-record')->assertOk()
            ->assertSee('Abroad recorded outcome')->assertDontSee('Home recorded outcome');
    }

    public function test_courses_history_and_overview_use_verified_fields_and_cannot_write(): void
    {
        $home = $this->campus('Home');
        $section = CourseOffering::create([
            'institution_id' => $home['institution']->id, 'course_id' => $home['course']->id, 'academic_term_id' => $home['term']->id,
            'section_code' => 'SECTION-1', 'status' => 'active', 'delivery_mode' => 'online',
        ]);
        $active = CourseEnrollment::create([
            'institution_id' => $home['institution']->id, 'student_id' => $home['student']->id, 'course_id' => $home['course']->id,
            'course_offering_id' => $section->id, 'academic_term_id' => $home['term']->id, 'status' => 'enrolled',
            'enrolled_at' => '2026-03-01', 'final_grade' => 'UNFINALIZED', 'completion_override_reason' => 'PRIVATE REVIEW',
        ]);
        $direct = CourseEnrollment::create([
            'institution_id' => $home['institution']->id, 'student_id' => $home['student']->id, 'course_id' => $home['course']->id,
            'status' => 'completed', 'completed_at' => '2026-04-02',
        ]);
        $record = $this->record($home, $home['student'], ['course_enrollment_id' => $direct->id, 'course_title' => 'Durable Course Title']);
        $this->record($home, $home['student'], ['status' => 'in_progress', 'course_title' => 'Unfinalized academic record']);
        $home['course']->update(['scheduling_basis' => 'completion_date']);

        $this->actingAs($home['account'])->get('/student')->assertOk()
            ->assertSee('Home Learner')->assertSee('HOME-1')->assertSee('Administrative student status')
            ->assertSee('Current course enrollments')->assertSee('Completed courses on record')->assertSee('Master of Theology');
        $this->get('/student?section=courses')->assertOk()->assertSee('SECTION-1')->assertSee('Mar 1, 2026')
            ->assertSee('Durable Course Title')->assertSee('Finalized outcome')
            ->assertDontSee('UNFINALIZED')->assertDontSee('PRIVATE REVIEW');
        $this->get('/student?section=academic-record')->assertOk()->assertSee('Durable Course Title')
            ->assertSee('Spring 2026 (2025-2026)')->assertSee('3.00')->assertSee('Apr 2, 2026')
            ->assertDontSee('Unfinalized academic record')->assertDontSee('PRIVATE RECORD NOTE');
        $this->post('/student?section=courses', ['enrollment_id' => $active->id, 'status' => 'withdrawn'])->assertMethodNotAllowed();
        $this->patch('/student?section=academic-record', ['record_id' => $record->id, 'final_grade' => 'F'])->assertMethodNotAllowed();
        $this->assertSame('enrolled', $active->fresh()->status);
        $this->assertSame('A', $record->fresh()->final_grade);
        $this->assertDatabaseCount('academic_records', 2);
    }

    public function test_degree_progress_projects_existing_audit_and_awards_use_frozen_public_fields_only(): void
    {
        $home = $this->campus('Home');
        $program = $home['program'];
        $context = ['institution_id' => $home['institution']->id, 'program_id' => $program->id];
        $group = ProgramRequirementGroup::create($context + ['name' => 'Core requirements', 'group_type' => 'core']);
        $requirement = ProgramRequirement::create($context + [
            'program_requirement_group_id' => $group->id, 'name' => 'Formation assessment',
            'requirement_type' => 'non_course_requirement', 'notes' => 'PRIVATE REQUIREMENT NOTE',
        ]);
        ProgramRequirement::create($context + [
            'program_requirement_group_id' => $group->id, 'name' => 'Capstone review',
            'requirement_type' => 'non_course_requirement',
        ]);
        StudentRequirementEvidence::create($context + [
            'student_id' => $home['student']->id, 'program_requirement_id' => $requirement->id,
            'status' => 'approved', 'evidence_title' => 'PRIVATE EVIDENCE TITLE',
        ]);
        DegreeConferral::create($context + [
            'student_id' => $home['student']->id, 'program_code' => 'ORIGINAL',
            'program_title' => 'Captured Credential Program', 'credential_type' => 'M.Th.',
            'conferred_on' => '2026-05-03', 'decided_at' => now(), 'conferred_by_user_id' => $home['admin']->id,
            'registrar_remarks' => 'PRIVATE AWARD REMARK', 'eligibility_snapshot' => ['private' => 'PRIVATE AWARD EVIDENCE'],
        ]);
        $program->update(['title' => 'Current Catalog Program']);
        $this->actingAs($home['account'])->get('/student?section=degree-progress')->assertOk()
            ->assertSee('This advisory view')->assertSee('Core requirements')->assertSee('Formation assessment')
            ->assertSee('Approved or waived evidence')->assertSee('Capstone review')->assertSee('Incomplete')
            ->assertDontSee('PRIVATE EVIDENCE TITLE')->assertDontSee('PRIVATE REQUIREMENT NOTE');
        $this->get('/student?section=awards')->assertOk()
            ->assertSee('Captured Credential Program (ORIGINAL)')->assertSee('M.Th.')->assertSee('May 3, 2026')
            ->assertDontSee('Current Catalog Program')->assertDontSee('PRIVATE AWARD REMARK')->assertDontSee('PRIVATE AWARD EVIDENCE');
    }

    private function account(Institution $institution, string $role): User
    {
        $user = User::factory()->create(['current_institution_id' => $institution->id]);
        $user->institutions()->attach($institution, ['role' => $role]);

        return $user;
    }

    private function membership(User $user, Institution $institution): int
    {
        return (int) $user->institutions()->whereKey($institution->id)->firstOrFail()->pivot->id;
    }

    private function student(Institution $institution, string $name, ?Program $program = null): Student
    {
        return Student::create([
            'institution_id' => $institution->id, 'program_id' => $program?->id, 'first_name' => $name,
            'last_name' => 'Learner', 'email' => strtolower($name).'@example.test', 'student_number' => strtoupper($name).'-1',
        ]);
    }

    private function campus(string $name): array
    {
        $institution = Institution::create(['name' => $name.' Seminary', 'slug' => strtolower($name), 'type' => 'seminary']);
        $tenant = ['institution_id' => $institution->id];
        $program = Program::create($tenant + ['code' => 'MTH', 'title' => 'Master of Theology', 'slug' => 'mth']);
        $student = $this->student($institution, $name, $program);
        $course = Course::create($tenant + ['code' => 'BIB-101', 'title' => 'Biblical Studies', 'slug' => 'bible']);
        $term = AcademicTerm::create($tenant + [
            'name' => 'Spring 2026', 'code' => 'SPRING', 'term_type' => 'spring', 'academic_year' => '2025-2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-05-01',
        ]);
        $admin = $this->account($institution, 'admin');
        $account = $this->account($institution, 'student');
        AcademicIdentityAccess::link($admin, $student, $this->membership($account, $institution));

        return compact('institution', 'program', 'student', 'course', 'term', 'account', 'admin');
    }

    private function record(array $context, Student $student, array $attributes): AcademicRecord
    {
        return AcademicRecord::create($attributes + [
            'institution_id' => $context['institution']->id, 'student_id' => $student->id,
            'course_id' => $context['course']->id, 'course_code' => 'BIB-101', 'course_title' => 'Biblical Studies',
            'final_grade' => 'A', 'credits_attempted' => 3, 'credits_earned' => 3, 'completed_at' => '2026-04-02',
            'status' => 'completed', 'notes' => 'PRIVATE RECORD NOTE',
        ]);
    }
}

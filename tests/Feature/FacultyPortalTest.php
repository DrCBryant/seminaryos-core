<?php

namespace Tests\Feature;

use App\Filament\Resources\Faculty\Pages\EditFaculty;
use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\MasterAssessment;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\SectionAssignment;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\StudentRequirementEvidence;
use App\Models\StudentSectionSubmission;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FacultyPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_requires_a_current_active_faculty_identity_and_shares_the_student_session(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $actor = $this->account($first);
        $faculty = $this->faculty($first, 'FirstFaculty');
        $student = $this->student($first, 'FirstStudent');
        $secondFaculty = $this->faculty($second, 'SecondFaculty');
        $firstMentee = $this->student($first, 'FirstMentee');
        $secondMentee = $this->student($second, 'SecondMentee');
        $faculty->mentees()->attach($firstMentee);
        $secondFaculty->mentees()->attach($secondMentee);
        [$course, $term, $section] = $this->courseContext($first);
        $firstTeaching = $this->assignment($faculty, $course, $term, $section);
        $this->link($actor, $student);

        $this->get('/faculty')->assertRedirect('/faculty/login');
        $this->actingAs($actor)->get('/faculty')->assertForbidden();
        $this->link($actor, $faculty);
        Filament::setCurrentPanel(Filament::getPanel('faculty'));
        auth()->logout();
        Livewire::test(Login::class)->fillForm(['email' => $actor->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($actor);
        $this->get('/faculty')->assertOk()->assertSee('FirstFaculty')->assertSee('Student Portal')
            ->assertDontSee('Registrar workspace')->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $this->get('/faculty?section=mentees&selection='.$firstMentee->id)->assertOk();
        $this->get('/faculty?section=mentees&selection='.$secondMentee->id)->assertNotFound();
        $this->get('/student')->assertOk()->assertSee('Faculty Portal');
        $this->get('/admin')->assertForbidden();

        $actor->institutions()->attach($second->id, ['role' => 'admin', 'status' => 'active']);
        $this->link($actor, $secondFaculty);
        $actor->update(['current_institution_id' => $second->id]);
        $this->actingAs($actor->fresh());
        $this->get('/faculty')->assertOk()->assertSee('SecondFaculty')->assertSee('Registrar workspace')
            ->assertDontSee('FirstFaculty')->assertDontSee('Student Portal');
        $this->get('/faculty?section=mentees&selection='.$secondMentee->id)->assertOk()->assertSee('SecondMentee');
        $this->get('/faculty?section=mentees&selection='.$firstMentee->id)->assertNotFound();
        $this->get('/faculty?section=courses&selection='.$firstTeaching->id)->assertNotFound();
        $actor->institutions()->updateExistingPivot($second->id, ['status' => 'inactive']);
        $this->get('/faculty')->assertForbidden();
        $actor->institutions()->updateExistingPivot($second->id, ['status' => 'active']);
        $second->update(['status' => 'inactive']);
        $this->get('/faculty')->assertForbidden();
        $second->update(['status' => 'active']);
        $actor->update(['status' => 'inactive']);
        $this->actingAs($actor->fresh());
        $this->get('/faculty')->assertForbidden();
    }

    public function test_mentorship_is_provisioned_by_an_administrator_and_rejects_cross_institution_links(): void
    {
        $institution = $this->institution('local');
        $foreign = $this->institution('foreign');
        $faculty = $this->faculty($institution, 'LocalFaculty');
        $student = $this->student($institution, 'LocalStudent');
        $outsider = $this->student($foreign, 'ForeignStudent');
        $staff = $this->account($institution, 'staff');
        $admin = $this->account($institution, 'admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($staff);
        Livewire::test(EditFaculty::class, ['record' => $faculty->id])->assertDontSee('Assign Mentee');
        $this->actingAs($admin);
        $page = Livewire::test(EditFaculty::class, ['record' => $faculty->id]);
        $page->callAction('assignMentee', ['student_id' => $outsider->id])->assertHasActionErrors(['student_id']);
        $page->setActionData(['student_id' => $student->id])->callMountedAction()->assertHasNoActionErrors();
        $this->assertTrue($faculty->mentees()->whereKey($student->id)->exists());
        $page->callAction('removeMentee', ['student_id' => $student->id])->assertHasNoActionErrors();
        $this->assertFalse($faculty->mentees()->whereKey($student->id)->exists());

        $this->expectException(QueryException::class);
        $faculty->mentees()->attach($outsider->id);
    }

    public function test_mentor_sees_only_assigned_students_and_course_completion_does_not_establish_mastery(): void
    {
        $institution = $this->institution('competency');
        $tenant = ['institution_id' => $institution->id];
        $mentor = $this->faculty($institution, 'Mentor');
        $other = $this->faculty($institution, 'OtherMentor');
        $actor = $this->account($institution);
        $this->link($actor, $mentor);
        [$course, $term, $section] = $this->courseContext($institution);
        $teaching = $this->assignment($other, $course, $term, $section);
        $program = Program::create($tenant + ['title' => 'Competency Program', 'code' => 'CP', 'slug' => 'cp', 'credential_type' => 'Certificate']);
        $mentee = $this->student($institution, 'AssignedMentee');
        $unrelated = $this->student($institution, 'PrivateStudent');
        $other->mentees()->attach($unrelated);
        $mentor->mentees()->attach($mentee);
        $mentee->update(['program_id' => $program->id]);
        $programContext = $tenant + ['program_id' => $program->id];
        $group = ProgramRequirementGroup::create($programContext + ['name' => 'Core', 'group_type' => 'core', 'is_active' => true]);
        $requirement = ProgramRequirement::create($programContext + [
            'program_requirement_group_id' => $group->id, 'course_id' => $course->id, 'name' => 'Interpretive mastery',
            'requirement_type' => ProgramRequirement::TYPE_COMPETENCY, 'is_active' => true,
        ]);
        AcademicRecord::create($tenant + [
            'student_id' => $mentee->id, 'course_id' => $course->id, 'course_code' => $course->code, 'course_title' => $course->title,
            'status' => 'completed', 'final_grade' => 'A', 'credits_earned' => 3, 'is_passing' => true, 'notes' => 'Private record notes',
        ]);
        StudentRequirementEvidence::create($programContext + [
            'student_id' => $mentee->id, 'program_requirement_id' => $requirement->id, 'evidence_title' => 'Annotated reading',
            'status' => 'approved', 'notes' => 'Internal evidence notes',
        ]);
        $assessment = MasterAssessment::create($tenant + [
            'course_offering_id' => $section->id, 'title' => 'Section interpretation assessment', 'status' => MasterAssessment::STATUS_ACTIVE,
        ]);
        StudentMasterAssessmentAttempt::create($tenant + [
            'master_assessment_id' => $assessment->id, 'course_offering_id' => $section->id, 'student_id' => $mentee->id,
            'status' => StudentMasterAssessmentAttempt::STATUS_PASSED, 'assessor_notes' => 'Private assessment notes',
        ]);
        $pending = MasterAssessment::create($tenant + [
            'course_offering_id' => $section->id, 'title' => 'Pending assessment', 'status' => MasterAssessment::STATUS_ACTIVE,
        ]);
        StudentMasterAssessmentAttempt::create($tenant + [
            'master_assessment_id' => $pending->id, 'course_offering_id' => $section->id, 'student_id' => $mentee->id,
            'status' => StudentMasterAssessmentAttempt::STATUS_SUBMITTED,
        ]);

        $this->actingAs($actor)->get('/faculty')->assertOk()->assertSee('AssignedMentee')->assertDontSee('PrivateStudent')
            ->assertSee('<strong>1</strong><span>Mentee assessments submitted</span>', false)
            ->assertSee('<strong>0</strong><span>Teaching assignments</span>', false);
        $this->get('/faculty?section=mentees&selection='.$mentee->id)->assertOk()
            ->assertSee('Competency Program')->assertSee('Interpretive mastery')->assertSee('Not Evaluated')
            ->assertSee('Foundations')->assertSee('Annotated reading')->assertSee('Section interpretation assessment')
            ->assertSee('Passed')->assertSee('Submitted')->assertDontSee($mentee->email)
            ->assertDontSee('Private record notes')->assertDontSee('Internal evidence notes')->assertDontSee('Private assessment notes');
        $this->get('/faculty?section=mentees&selection='.$unrelated->id)->assertNotFound();
        $this->get('/faculty?section=courses&selection='.$teaching->id)->assertNotFound();

        $mentor->mentees()->detach($mentee);
        $this->get('/faculty?section=mentees&selection='.$mentee->id)->assertNotFound();
    }

    public function test_instructor_sees_exact_section_and_direct_course_term_with_no_grade_write(): void
    {
        $institution = $this->institution('traditional');
        $tenant = ['institution_id' => $institution->id];
        $faculty = $this->faculty($institution, 'Instructor');
        $other = $this->faculty($institution, 'OtherInstructor');
        $actor = $this->account($institution);
        $this->link($actor, $faculty);
        [$course, $term, $section] = $this->courseContext($institution);
        $otherSection = CourseOffering::create($tenant + [
            'course_id' => $course->id, 'academic_term_id' => $term->id, 'section_code' => 'PRIVATE', 'delivery_mode' => 'online', 'status' => 'in_progress',
        ]);
        $current = $this->assignment($faculty, $course, $term, $section);
        $direct = $this->assignment($faculty, $course, $term);
        $unauthorized = $this->assignment($other, $course, $term, $otherSection);
        $enrolled = $this->student($institution, 'SectionStudent');
        $directStudent = $this->student($institution, 'DirectStudent');
        $offTerm = $this->student($institution, 'OffTermStudent');
        $hidden = $this->student($institution, 'HiddenStudent');
        $enrollment = $this->enroll($enrolled, $course, $term, $section);
        $this->enroll($directStudent, $course, $term);
        $this->enroll($offTerm, $course);
        $this->enroll($hidden, $course, $term, $otherSection);
        $record = AcademicRecord::create($tenant + [
            'student_id' => $enrolled->id, 'course_id' => $course->id, 'course_enrollment_id' => $enrollment->id,
            'course_code' => $course->code, 'course_title' => $course->title, 'final_grade' => 'A', 'status' => 'completed',
        ]);
        $attendance = AttendanceSession::create($tenant + [
            'course_offering_id' => $section->id, 'course_id' => $course->id, 'academic_term_id' => $term->id, 'session_date' => '2026-10-01', 'status' => 'held',
        ]);
        AttendanceRecord::create($tenant + [
            'course_offering_id' => $section->id, 'attendance_session_id' => $attendance->id,
            'course_enrollment_id' => $enrollment->id, 'student_id' => $enrolled->id, 'status' => 'present',
        ]);
        $assignment = SectionAssignment::create($tenant + [
            'course_offering_id' => $section->id, 'title' => 'Reading response', 'assignment_type' => 'assignment', 'requirement_basis' => 'completion', 'status' => 'active', 'is_required' => true,
        ]);
        StudentSectionSubmission::create($tenant + [
            'course_offering_id' => $section->id, 'section_assignment_id' => $assignment->id, 'course_enrollment_id' => $enrollment->id,
            'student_id' => $enrolled->id, 'status' => 'submitted', 'reviewer_notes' => 'Private reviewer notes',
        ]);

        $this->actingAs($actor)->get('/faculty')->assertOk()
            ->assertSee('<strong>1</strong><span>Section submissions awaiting review</span>', false);
        $this->get('/faculty?section=courses&selection='.$current->id)->assertOk()
            ->assertSee('SectionStudent')->assertSee('Satisfied')->assertSee('2026-10-01 · Present')
            ->assertSee('Reading response · Submitted')->assertSee('>A</td>', false)
            ->assertDontSee('Private reviewer notes')->assertDontSee('DirectStudent')->assertDontSee('HiddenStudent');
        $this->get('/faculty?section=courses&selection='.$direct->id)->assertOk()
            ->assertSee('DirectStudent')->assertDontSee('SectionStudent')->assertDontSee('HiddenStudent')->assertDontSee('OffTermStudent');
        $this->get('/faculty?section=courses&selection='.$unauthorized->id)->assertNotFound();
        $this->get('/faculty?section=mentees&selection='.$enrolled->id)->assertNotFound();
        $this->post('/faculty', ['grade' => 'F'])->assertMethodNotAllowed();
        $this->get('/admin/master-assessments/create')->assertForbidden();
        $this->assertSame('A', $record->fresh()->final_grade);

        $faculty->mentees()->attach($directStudent);
        $this->get('/faculty?section=mentees&selection='.$directStudent->id)->assertOk()->assertSee('DirectStudent');
        $current->update(['status' => 'cancelled']);
        $this->get('/faculty?section=courses&selection='.$current->id)->assertNotFound();
        $this->get('/faculty?section=courses&selection='.$direct->id)->assertOk()->assertDontSee('SectionStudent');
    }

    private function institution(string $slug): Institution
    {
        return Institution::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'seminary', 'status' => 'active']);
    }

    private function account(Institution $institution, string $role = 'faculty'): User
    {
        $user = User::factory()->create(['current_institution_id' => $institution->id, 'status' => 'active']);
        $user->institutions()->attach($institution->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function link(User $actor, Student|Faculty $identity): void
    {
        $admin = $this->account($identity->institution, 'admin');
        $id = $actor->institutions()->whereKey($identity->institution_id)->firstOrFail()->pivot->id;
        AcademicIdentityAccess::link($admin, $identity, $id);
    }

    private function faculty(Institution $institution, string $name): Faculty
    {
        return Faculty::create(['institution_id' => $institution->id, 'first_name' => $name, 'last_name' => 'Faculty', 'email' => $name.'@example.test']);
    }

    private function student(Institution $institution, string $name): Student
    {
        return Student::create([
            'institution_id' => $institution->id, 'first_name' => $name, 'last_name' => 'Student', 'email' => $name.'@example.test',
            'student_number' => $name, 'status' => 'active',
        ]);
    }

    private function courseContext(Institution $institution): array
    {
        $tenant = ['institution_id' => $institution->id];
        $course = Course::create($tenant + ['title' => 'Foundations', 'code' => 'FND', 'slug' => 'foundations']);
        $term = AcademicTerm::create($tenant + [
            'name' => 'Fall', 'code' => 'F26', 'academic_year' => '2026–27', 'term_type' => 'fall',
            'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
        ]);
        $section = CourseOffering::create($tenant + [
            'course_id' => $course->id, 'academic_term_id' => $term->id, 'section_code' => 'A',
            'delivery_mode' => 'online', 'status' => 'in_progress', 'progress_basis' => CourseOffering::PROGRESS_BASIS_ATTENDANCE,
        ]);

        return [$course, $term, $section];
    }

    private function assignment(Faculty $faculty, Course $course, AcademicTerm $term, ?CourseOffering $section = null): TeachingAssignment
    {
        return $faculty->teachingAssignments()->create([
            'institution_id' => $faculty->institution_id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'course_offering_id' => $section?->id, 'status' => 'active', 'role' => $section ? 'primary_instructor' : 'co_instructor',
        ]);
    }

    private function enroll(Student $student, Course $course, ?AcademicTerm $term = null, ?CourseOffering $offering = null): CourseEnrollment
    {
        return $student->courseEnrollments()->create([
            'institution_id' => $student->institution_id, 'course_id' => $course->id, 'academic_term_id' => $term?->id,
            'course_offering_id' => $offering?->id, 'status' => 'enrolled',
        ]);
    }
}

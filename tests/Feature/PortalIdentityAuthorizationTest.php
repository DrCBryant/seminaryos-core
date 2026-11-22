<?php

namespace Tests\Feature;

use App\Filament\Resources\Faculty\Pages\EditFaculty;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PortalIdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_inspect_replace_and_remove_each_identity_in_filament(): void
    {
        $context = $this->context('first');
        $institution = $context['institution'];
        $admin = $this->account($institution, 'admin');
        $dual = $this->account($institution, 'faculty');
        $replacement = $this->account($institution, 'student');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $membershipId = $this->membershipId($dual, $institution);
        $student = Livewire::test(EditStudent::class, ['record' => $context['student']->id]);
        $faculty = Livewire::test(EditFaculty::class, ['record' => $context['faculty']->id]);
        $student->callAction('linkAcademicAccount', ['membership_id' => $membershipId])->assertHasNoActionErrors();
        $faculty->callAction('linkAcademicAccount', ['membership_id' => $membershipId])->assertHasNoActionErrors();
        $this->assertSame($context['student']->id, AcademicIdentityAccess::student($dual)?->id);
        $this->assertSame($context['faculty']->id, AcademicIdentityAccess::faculty($dual)?->id);

        $student->mountAction('linkAcademicAccount')->assertActionDataSet(['membership_id' => $membershipId]);
        $student->setActionData(['membership_id' => $this->membershipId($replacement, $institution)])->callMountedAction();
        $this->assertNull(AcademicIdentityAccess::student($dual));
        $this->assertSame($context['student']->id, AcademicIdentityAccess::student($replacement)?->id);

        $faculty->callAction('linkAcademicAccount', ['membership_id' => null])->assertHasNoActionErrors();
        $this->assertNull(AcademicIdentityAccess::faculty($dual));
        $this->assertModelExists($context['faculty']);
        $this->assertModelExists($context['record']);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('faculty', 1);
    }

    public function test_dual_identity_in_one_institution_has_separate_permissions_for_each_action(): void
    {
        $context = $this->context('first');
        $institution = $context['institution'];
        $admin = $this->account($institution, 'admin');
        $dual = $this->account($institution, 'student');
        $peer = $this->student($institution, 'peer');
        $peerRecord = $context['record']->replicate(['uuid']);
        $peerRecord->student_id = $peer->id;
        $peerRecord->save();
        $otherFaculty = Faculty::create(['institution_id' => $institution->id, 'first_name' => 'Other', 'last_name' => 'Instructor', 'email' => 'other-faculty@example.test']);
        $peerAssignment = $context['assignment']->replicate(['uuid']);
        $peerAssignment->faculty_id = $otherFaculty->id;
        $peerAssignment->save();

        AcademicIdentityAccess::link($admin, $context['faculty'], $this->membershipId($dual, $institution));
        $this->assertTrue($dual->can('view-own-faculty-identity', $context['faculty']));
        $this->assertTrue($dual->can('view-assigned-teaching-assignment', $context['assignment']));
        $this->assertFalse($dual->can('view-own-academic-record', $context['record']));

        AcademicIdentityAccess::link($admin, $context['student'], $this->membershipId($dual, $institution));
        $this->assertTrue($dual->can('view-own-student-identity', $context['student']));
        $this->assertTrue($dual->can('view-own-academic-record', $context['record']));
        $this->assertTrue($dual->can('view-assigned-teaching-section', $context['section']));
        $this->assertFalse($dual->can('view-own-academic-record', $peerRecord));
        $this->assertFalse($dual->can('view-own-faculty-identity', $otherFaculty));
        $this->assertFalse($dual->can('view-assigned-teaching-assignment', $peerAssignment));
        $context['assignment']->update(['status' => 'cancelled']);
        $this->assertFalse($dual->can('view-assigned-teaching-assignment', $context['assignment']));
        $this->assertFalse($dual->can('view-assigned-teaching-section', $context['section']));
        $this->assertTrue($dual->can('view-own-academic-record', $context['record']));

        $context['assignment']->update(['status' => 'active']);
        AcademicIdentityAccess::link($admin, $context['faculty'], null);
        $this->assertTrue($dual->can('view-own-academic-record', $context['record']));
        $this->assertFalse($dual->can('view-assigned-teaching-assignment', $context['assignment']));
        $this->assertFalse($dual->can('view-assigned-teaching-section', $context['section']));
    }

    public function test_switching_between_institutions_switches_the_effective_identity_and_role(): void
    {
        $home = $this->context('home');
        $other = $this->context('other');
        $homeAdmin = $this->account($home['institution'], 'admin');
        $otherAdmin = $this->account($other['institution'], 'admin');
        $dual = $this->account($home['institution'], 'admin');
        $dual->institutions()->attach($other['institution'], ['role' => 'faculty']);

        AcademicIdentityAccess::link($homeAdmin, $home['student'], $this->membershipId($dual, $home['institution']));
        AcademicIdentityAccess::link($otherAdmin, $other['faculty'], $this->membershipId($dual, $other['institution']));
        $this->actingAs($dual);
        $this->assertTrue($dual->canAccessRegistrar());
        $this->assertTrue($dual->can('view-own-academic-record', $home['record']));
        $this->assertFalse($dual->can('view-assigned-teaching-assignment', $other['assignment']));
        $this->assertSame([$home['record']->id], AcademicRecord::query()->pluck('id')->all());

        $dual->update(['current_institution_id' => $other['institution']->id]);
        $this->assertFalse($dual->canAccessRegistrar());
        $this->assertFalse($dual->can('view-own-academic-record', $home['record']));
        $this->assertTrue($dual->can('view-assigned-teaching-assignment', $other['assignment']));
        $this->assertFalse(AcademicIdentityAccess::canManageLinks($dual, $other['faculty']));
        $this->assertSame([$other['record']->id], AcademicRecord::query()->pluck('id')->all());
    }

    public function test_inactive_user_inactive_membership_and_missing_context_fail_closed(): void
    {
        $context = $this->context('home');
        $institution = $context['institution'];
        $admin = $this->account($institution, 'admin');
        $user = $this->account($institution, 'faculty');
        $membershipId = $this->membershipId($user, $institution);
        AcademicIdentityAccess::link($admin, $context['student'], $membershipId);
        AcademicIdentityAccess::link($admin, $context['faculty'], $membershipId);
        $this->actingAs($user);

        foreach (['suspended', 'inactive'] as $status) {
            $user->update(['status' => $status]);
            $this->assertNull(AcademicIdentityAccess::student($user));
            $this->assertNull(AcademicIdentityAccess::faculty($user));
            $this->assertSame(0, AcademicRecord::query()->count());
        }

        $user->update(['status' => 'active']);
        $user->institutions()->updateExistingPivot($institution->id, ['status' => 'inactive']);
        $this->assertFalse($user->can('view-own-academic-record', $context['record']));
        $this->assertSame(0, AcademicRecord::query()->count());
        $user->institutions()->updateExistingPivot($institution->id, ['status' => 'active']);
        $user->update(['current_institution_id' => null]);
        $this->assertFalse($user->can('view-assigned-teaching-assignment', $context['assignment']));
        $this->assertSame(0, Student::query()->count());

        auth()->logout();
        $this->assertSame(1, AcademicRecord::query()->count());
    }

    public function test_linking_rejects_foreign_memberships_and_database_constraints_validate_both_parents(): void
    {
        $home = $this->context('home');
        $foreign = $this->context('foreign');
        $admin = $this->account($home['institution'], 'admin');
        $foreignUser = $this->account($foreign['institution'], 'student');
        $foreignMembership = $this->membershipId($foreignUser, $foreign['institution']);

        try {
            AcademicIdentityAccess::link($admin, $home['student'], $foreignMembership);
            $this->fail('Cross-institution linking must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership_id', $exception->errors());
        }

        foreach (['student' => 'student_identity_links', 'faculty' => 'faculty_identity_links'] as $kind => $table) {
            foreach ([$home['institution']->id, $foreign['institution']->id] as $claimedInstitution) {
                try {
                    DB::table($table)->insert([
                        'identity_id' => $home[$kind]->id,
                        'membership_id' => $foreignMembership,
                        'institution_id' => $claimedInstitution,
                    ]);
                    $this->fail('The database must enforce both tenant foreign keys.');
                } catch (QueryException $exception) {
                    $this->assertStringContainsStringIgnoringCase('foreign key constraint', $exception->getMessage());
                }
            }
        }

        $this->assertDatabaseCount('student_identity_links', 0);
        $this->assertDatabaseCount('faculty_identity_links', 0);
    }

    public function test_an_account_can_only_link_one_record_of_each_identity_kind_per_institution(): void
    {
        $context = $this->context('home');
        $campus = $context['institution'];
        $admin = $this->account($campus, 'admin');
        $user = $this->account($campus, 'student');
        $membershipId = $this->membershipId($user, $campus);
        AcademicIdentityAccess::link($admin, $context['student'], $membershipId);
        $peer = $this->student($campus, 'peer');

        try {
            AcademicIdentityAccess::link($admin, $peer, $membershipId);
            $this->fail('A second student link must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership_id', $exception->errors());
        }

        $this->assertSame($context['student']->id, AcademicIdentityAccess::student($user)?->id);
        $this->assertDatabaseCount('student_identity_links', 1);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('student_identity_links')->insert([
            'identity_id' => $peer->id,
            'membership_id' => $membershipId,
            'institution_id' => $campus->id,
        ]);
    }

    public function test_registrar_panel_and_print_require_registrar_access_and_linking_requires_admin(): void
    {
        $context = $this->context('home');
        $campus = $context['institution'];
        $admin = $this->account($campus, 'admin');
        $staff = $this->account($campus, 'staff');
        $faculty = $this->account($campus, 'faculty');
        $transcript = OfficialTranscript::create([
            'institution_id' => $campus->id, 'student_id' => $context['student']->id,
            'transcript_number' => 'PORTAL-BOUNDARY', 'status' => 'issued',
        ]);

        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
        $this->actingAs($admin)->get('/admin/students')->assertOk();
        $this->actingAs($staff)->get('/admin/students')->assertOk();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditStudent::class, ['record' => $context['student']->id])->assertActionHidden('linkAcademicAccount');
        $this->actingAs($faculty)->get('/admin/students')->assertForbidden();
        $this->get(route('official-transcripts.print', $transcript->uuid))->assertForbidden();

        try {
            AcademicIdentityAccess::link($staff, $context['student'], $this->membershipId($faculty, $campus));
            $this->fail('Staff must not change an identity link.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $staff->update(['current_institution_id' => null]);
        $this->actingAs($staff)->get('/admin/students')->assertForbidden();

        $admin->update(['status' => 'suspended']);
        $this->actingAs($admin)->get('/admin/students')->assertForbidden();
        $this->assertDatabaseCount('student_identity_links', 0);
    }

    public function test_existing_filament_login_admits_registrar_role_only_in_an_active_context(): void
    {
        $campus = $this->context('home')['institution'];
        $admin = $this->account($campus, 'admin');
        $faculty = $this->account($campus, 'faculty');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)->fillForm(['email' => $faculty->email, 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();

        Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_suspending_or_removing_an_account_preserves_both_identities_and_academic_history(): void
    {
        $context = $this->context('home');
        $campus = $context['institution'];
        $admin = $this->account($campus, 'admin');
        $user = $this->account($campus, 'student');
        $membershipId = $this->membershipId($user, $campus);
        AcademicIdentityAccess::link($admin, $context['student'], $membershipId);
        AcademicIdentityAccess::link($admin, $context['faculty'], $membershipId);

        $user->update(['status' => 'suspended']);
        $this->assertNull(AcademicIdentityAccess::student($user));
        $this->assertDatabaseCount('student_identity_links', 1);
        $this->assertDatabaseCount('faculty_identity_links', 1);
        $user->delete();
        $this->assertDatabaseCount('student_identity_links', 0);
        $this->assertDatabaseCount('faculty_identity_links', 0);
        foreach (['student', 'faculty', 'record', 'assignment'] as $key) {
            $this->assertModelExists($context[$key]);
        }
    }

    private function membershipId(User $user, Institution $institution): int
    {
        return (int) $user->institutions()->whereKey($institution->id)->firstOrFail()->pivot->id;
    }

    private function account(Institution $institution, string $role): User
    {
        $actor = User::factory()->create(['current_institution_id' => $institution->id]);
        $actor->institutions()->attach($institution, ['role' => $role]);

        return $actor;
    }

    private function student(Institution $institution, string $number): Student
    {
        return Student::create([
            'institution_id' => $institution->id, 'first_name' => 'Academic', 'last_name' => 'Student',
            'email' => $number.'@example.test', 'student_number' => $number,
        ]);
    }

    private function context(string $key): array
    {
        $institution = Institution::create(['name' => ucfirst($key), 'slug' => $key, 'type' => 'seminary']);
        $tenant = ['institution_id' => $institution->id];
        $student = $this->student($institution, $key);
        $faculty = Faculty::create($tenant + ['first_name' => 'Academic', 'last_name' => 'Instructor', 'email' => $key.'-faculty@example.test']);
        $course = Course::create($tenant + ['code' => 'BIB-101', 'title' => 'Bible', 'slug' => 'bible']);
        $term = AcademicTerm::create($tenant + [
            'name' => 'Spring', 'code' => 'SPR-2026', 'academic_year' => '2025–26',
            'term_type' => 'spring', 'start_date' => '2026-01-01', 'end_date' => '2026-05-01',
        ]);
        $section = CourseOffering::create($tenant + [
            'course_id' => $course->id, 'academic_term_id' => $term->id, 'section_code' => 'S1',
            'delivery_mode' => 'online', 'status' => 'in_progress',
        ]);
        $assignment = TeachingAssignment::create($tenant + [
            'faculty_id' => $faculty->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'course_offering_id' => $section->id, 'status' => 'active', 'role' => 'primary_instructor',
        ]);
        $record = AcademicRecord::create($tenant + [
            'student_id' => $student->id, 'course_id' => $course->id,
            'course_code' => $course->code, 'course_title' => $course->title, 'status' => 'completed',
        ]);

        return compact('institution', 'student', 'faculty', 'record', 'assignment', 'section');
    }
}

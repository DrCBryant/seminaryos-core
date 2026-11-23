<?php

namespace App\Support\Portals;

use App\Core\Scopes\InstitutionScope;
use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicIdentityAccess
{
    public static function student(User $actor): ?Student
    {
        return self::identity($actor, Student::class);
    }

    public static function faculty(User $actor): ?Faculty
    {
        return self::identity($actor, Faculty::class);
    }

    public static function viewAcademicRecord(User $actor, AcademicRecord $record): bool
    {
        $student = self::student($actor);

        return $student !== null && ! $record->trashed()
            && (int) $record->institution_id === (int) $student->institution_id
            && (int) $record->student_id === (int) $student->id;
    }

    public static function viewMentee(User $actor, Student $student): bool
    {
        $faculty = self::faculty($actor);

        return $faculty !== null && ! $student->trashed()
            && (int) $student->institution_id === (int) $faculty->institution_id
            && $faculty->mentees()->withoutGlobalScope(InstitutionScope::class)->whereKey($student->id)->exists();
    }

    public static function viewTeachingAssignment(User $actor, TeachingAssignment $assignment): bool
    {
        $faculty = self::faculty($actor);

        return $faculty !== null && ! $assignment->trashed()
            && (int) $assignment->institution_id === (int) $faculty->institution_id
            && (int) $assignment->faculty_id === (int) $faculty->id
            && in_array($assignment->status, ['assigned', 'active'], true);
    }

    public static function viewTeachingSection(User $actor, CourseOffering $offering): bool
    {
        $faculty = self::faculty($actor);

        return $faculty !== null
            && (int) $offering->institution_id === (int) $faculty->institution_id
            && $faculty->teachingAssignments()->withoutGlobalScope(InstitutionScope::class)
                ->where('institution_id', $offering->institution_id)
                ->where('course_offering_id', $offering->id)
                ->where('course_id', $offering->course_id)
                ->whereIn('status', ['assigned', 'active'])->exists();
    }

    public static function canManageLinks(User $actor, Student|Faculty $identity, bool $lock = false): bool
    {
        return ! $identity->trashed() && in_array(
            $actor->activeInstitutionMembership((int) $identity->institution_id, $lock)?->role,
            User::INSTITUTION_ADMIN_ROLES,
            true,
        );
    }

    public static function linkedMembershipId(Student|Faculty $identity): ?int
    {
        $membershipId = DB::table(self::linkTable($identity::class))
            ->where('identity_id', $identity->id)->where('institution_id', $identity->institution_id)->value('membership_id');

        return $membershipId === null ? null : (int) $membershipId;
    }

    public static function membershipAccounts(Student|Faculty $identity, bool $activeOnly = false): Builder
    {
        return DB::table('institution_user as memberships')->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.institution_id', $identity->institution_id)
            ->when($activeOnly, fn (Builder $query): Builder => $query->where('memberships.status', 'active')
                ->where('users.status', 'active')->whereNull('users.deleted_at'))
            ->select('memberships.id', 'memberships.status as membership_status', 'users.name', 'users.email',
                'users.status as account_status', 'users.deleted_at');
    }

    public static function link(User $actor, Student|Faculty $identity, ?int $membershipId): void
    {
        DB::transaction(function () use ($actor, $identity, $membershipId): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = $identity->newQuery()->withoutGlobalScope(InstitutionScope::class)
                ->where('institution_id', $identity->institution_id)->lockForUpdate()->findOrFail($identity->id);
            abort_unless(self::canManageLinks($actor, $current, lock: true), 403);

            $table = self::linkTable($current::class);
            if ($membershipId === null) {
                DB::table($table)->where('identity_id', $current->id)->delete();

                return;
            }

            $target = self::membershipAccounts($current, activeOnly: true)->where('memberships.id', $membershipId)
                ->lockForUpdate()->first();

            if ($target === null) {
                throw ValidationException::withMessages(['membership_id' => 'Select an active account with an active membership in this institution.']);
            }

            $existing = DB::table($table)->where('membership_id', $membershipId)->lockForUpdate()->first();
            if ($existing !== null && (int) $existing->identity_id !== (int) $current->id) {
                throw ValidationException::withMessages(['membership_id' => 'This account is already linked to another identity of this kind in this institution.']);
            }

            DB::table($table)->updateOrInsert(
                ['identity_id' => $current->id],
                ['institution_id' => $current->institution_id, 'membership_id' => $membershipId],
            );
        }, 3);
    }

    /** @template T of Student|Faculty
     * @param  class-string<T>  $type
     * @return T|null
     */
    private static function identity(User $actor, string $type): Student|Faculty|null
    {
        $membership = $actor->activeInstitutionMembership();

        if ($membership === null) {
            return null;
        }

        $model = new $type;
        $links = self::linkTable($type);

        return $type::query()->withoutGlobalScope(InstitutionScope::class)
            ->where($model->qualifyColumn('institution_id'), $membership->institution_id)
            ->whereIn($model->qualifyColumn('id'), DB::table($links)
                ->select('identity_id')->where('membership_id', $membership->id)
                ->where('institution_id', $membership->institution_id))
            ->first();
    }

    /** @param class-string<Student|Faculty> $type */
    private static function linkTable(string $type): string
    {
        return match ($type) {
            Student::class => 'student_identity_links',
            Faculty::class => 'faculty_identity_links',
        };
    }
}

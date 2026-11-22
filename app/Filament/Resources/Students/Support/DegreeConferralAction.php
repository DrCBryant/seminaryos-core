<?php

namespace App\Filament\Resources\Students\Support;

use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicRecord;
use App\Models\DegreeConferral;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DegreeConferralAction
{
    public static function make(): Action
    {
        return Action::make('conferDegree')
            ->label('Confer Degree')
            ->icon('heroicon-o-academic-cap')
            ->authorize(fn (Student $record): bool => self::authorized($record))
            ->visible(fn (Student $record): bool => $record->program_id !== null && ! $record->trashed())
            ->modalHeading('Confer Degree')
            ->modalWidth('7xl')
            ->requiresConfirmation()
            ->modalContent(function (Student $record) {
                $audit = self::audit($record);

                return view('filament.students.degree-conferral-review', [
                    'audit' => $audit,
                    'blockers' => self::blockers($record, $audit),
                ]);
            })
            ->schema([
                Hidden::make('program_id')->default(fn (Student $record) => $record->program_id),
                DatePicker::make('conferred_on')->label('Official conferral date')->required(),
                Textarea::make('registrar_remarks')->label('Registrar decision notes')->maxLength(5000),
            ])
            ->modalSubmitActionLabel('Confirm Degree Conferral')
            ->action(function (Student $record, array $data): void {
                try {
                    self::confer($record, $data);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Degree conferral blocked')
                        ->body($exception->validator->errors()->first())
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()->title('Degree conferred')->success()->send();
            });
    }

    public static function awardedCredentials(): Action
    {
        return Action::make('viewAwardedCredentials')
            ->label('Awarded Credentials')
            ->icon('heroicon-o-academic-cap')
            ->authorize(fn (Student $record): bool => StudentResource::canView($record) && self::hasMembership($record))
            ->modalSubmitAction(false)
            ->modalContent(fn (Student $record) => view('filament.students.awarded-credentials', [
                'awards' => $record->degreeConferrals()->where('institution_id', $record->institution_id)
                    ->with('conferredBy')->orderByDesc('conferred_on')->orderByDesc('id')->get(),
            ]));
    }

    /** @param array<string, mixed> $data */
    protected static function confer(Student $student, array $data): DegreeConferral
    {
        $data = Validator::make($data, [
            'program_id' => ['required', 'integer'],
            'conferred_on' => ['required', 'date_format:Y-m-d'],
            'registrar_remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($student, $data): DegreeConferral {
            $current = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $actor = User::query()->whereNull('deleted_at')->lockForUpdate()->find(auth()->id());
            abort_unless($actor && StudentResource::canEdit($current) && self::hasMembership($current, $actor, forConferral: true), 403);

            if ((int) $data['program_id'] !== (int) $current->program_id) {
                throw ValidationException::withMessages(['program_id' => 'The assigned program changed. Review the current program before conferring.']);
            }

            $audit = self::audit($current);
            $blockers = self::blockers($current, $audit, lockAward: true);

            if ($blockers !== []) {
                throw ValidationException::withMessages(['eligibility' => $blockers]);
            }

            $program = $current->program;

            return $current->degreeConferrals()->create([
                'institution_id' => $current->institution_id,
                'program_id' => $program->id,
                'program_code' => $program->code,
                'program_title' => $program->title,
                'credential_type' => trim($program->credential_type),
                'conferred_by_user_id' => $actor->id,
                'conferred_on' => $data['conferred_on'],
                'decided_at' => now(),
                'eligibility_snapshot' => self::snapshot($audit),
                'registrar_remarks' => $data['registrar_remarks'] ?? null,
            ]);
        }, 3);
    }

    private static function authorized(Student $student): bool
    {
        return StudentResource::canEdit($student) && self::hasMembership($student, forConferral: true);
    }

    private static function hasMembership(Student $student, ?User $actor = null, bool $forConferral = false): bool
    {
        $membership = ($actor ?? auth()->user())?->activeInstitutionMembership(
            (int) $student->institution_id,
            lock: $actor !== null,
        );

        return $membership !== null && (! $forConferral || in_array($membership->role, User::INSTITUTION_ADMIN_ROLES, true));
    }

    private static function audit(Student $student): array
    {
        $student->load([
            'program' => fn ($query) => $query->where('institution_id', $student->institution_id),
            'academicRecords' => fn ($query) => $query->where('institution_id', $student->institution_id),
            'studentRequirementEvidence' => fn ($query) => $query->where('program_id', $student->program_id),
            'programRequirementSubstitutions' => fn ($query) => $query->where('program_id', $student->program_id),
        ]);

        return DegreeAuditPreview::getViewData($student);
    }

    /** @return list<string> */
    private static function blockers(Student $student, array $audit, bool $lockAward = false): array
    {
        $program = $student->program;

        if (! $program || blank($program->credential_type) || blank($program->title) || blank($program->code)) {
            return ['Assign an institutional program with a configured credential designation.'];
        }

        $awards = $student->degreeConferrals()->where('program_id', $program->id);

        if ($lockAward ? $awards->lockForUpdate()->first(['id']) !== null : $awards->exists()) {
            return ['This student already holds an award for this program.'];
        }

        $groups = $audit['groupResults'];
        if ($groups->isEmpty() || $groups->contains(fn (array $group): bool => $group['status'] !== 'complete')) {
            return ['Every active requirement group must have a verified complete result.'];
        }

        $requirements = $groups->flatMap(fn (array $group) => $group['requirements']);
        $evaluatedIds = $requirements->map(fn (array $result) => $result['requirement']->id)->sort()->values();
        $activeIds = $program->programRequirements()->where('is_active', true)->pluck('id')->sort()->values();

        if ($evaluatedIds->all() !== $activeIds->all()) {
            return ['Active curriculum requirements fall outside the evaluated program groups.'];
        }

        $creditsByRecord = [];
        foreach ($groups as $group) {
            if ($group['minimumGpa'] !== null && $group['requirements']->contains(
                fn (array $result): bool => $result['matchedRecords']->contains(function (AcademicRecord|array $entry): bool {
                    $record = is_array($entry) ? $entry['record'] : $entry;

                    return $record->affects_gpa === true
                        && ($record->grade_points === null || (float) $record->credits_attempted <= 0);
                })
            )) {
                return ['A group GPA depends on incomplete academic record data.'];
            }
        }

        foreach ($requirements as $result) {
            $requirement = $result['requirement'];
            if ($requirement->minimum_grade !== null || $requirement->minimum_grade_points !== null
                || ! array_key_exists($requirement->requirement_type, ProgramRequirement::TYPES)) {
                return ['The degree audit cannot verify a configured requirement type or minimum grade.'];
            }

            if ($requirement->required_credits !== null && (float) $result['earnedCredits'] < (float) $requirement->required_credits) {
                return ['An evaluated requirement does not meet its configured credit minimum.'];
            }

            if (isset($result['substitution']) && ! $requirement->allow_substitution) {
                return ['A requirement depends on a substitution that the curriculum does not allow.'];
            }

            foreach ($result['matchedRecords'] as $entry) {
                $record = is_array($entry) ? $entry['record'] : $entry;
                $applied = is_array($entry) ? $entry['appliedCredits'] : $record->credits_earned;
                $creditsByRecord[$record->id] = ($creditsByRecord[$record->id] ?? 0) + (int) round((float) $applied * 100);
                if ($creditsByRecord[$record->id] > (int) round((float) $record->credits_earned * 100)) {
                    return ['The degree audit applies more credits from a record than the student earned.'];
                }
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function snapshot(array $audit): array
    {
        return [
            'overall_earned_credits' => $audit['overallEarnedCredits'],
            'overall_gpa' => $audit['overallGpa'],
            'groups' => $audit['groupResults']->map(fn (array $group): array => [
                'group_id' => $group['group']->id,
                'name' => $group['group']->name,
                'type' => $group['group']->group_type,
                'status' => $group['status'],
                'required_credits' => $group['requiredCredits'],
                'earned_credits' => $group['earnedCredits'],
                'minimum_gpa' => $group['minimumGpa'],
                'calculated_gpa' => $group['calculatedGpa'],
                'requirements' => $group['requirements']->map(fn (array $result): array => [
                    'requirement_id' => $result['requirement']->id,
                    'name' => $result['requirement']->name,
                    'type' => $result['requirement']->requirement_type,
                    'course_id' => $result['requirement']->course_id,
                    'required_credits' => $result['requirement']->required_credits,
                    'status' => $result['status'],
                    'basis' => $result['message'],
                    'evidence' => ($result['evidence'] ?? null)?->only(['id', 'status', 'evidence_title', 'completed_at', 'approved_at', 'approved_by_user_id']),
                    'substitution' => ($result['substitution'] ?? null)?->only(['id', 'status', 'approved_at', 'approved_by_user_id']),
                    'records' => $result['matchedRecords']->map(function (AcademicRecord|array $entry): array {
                        $record = is_array($entry) ? $entry['record'] : $entry;

                        return $record->only(['id', 'course_code', 'course_title', 'status', 'final_grade',
                            'credits_attempted', 'credits_earned', 'grade_points', 'earns_credit', 'is_passing', 'affects_gpa', 'completed_at'])
                            + ['applied_credits' => is_array($entry) ? $entry['appliedCredits'] : $record->credits_earned];
                    })->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}

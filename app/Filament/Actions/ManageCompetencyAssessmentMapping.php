<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ProgramRequirementGroups\ProgramRequirementGroupResource;
use App\Models\MasterAssessment;
use App\Models\ProgramRequirement;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCompetencyAssessmentMapping
{
    public static function make(bool $attach): Action
    {
        return Action::make($attach ? 'mapAssessment' : 'unmapAssessment')
            ->label($attach ? 'Map Master Assessment' : 'Remove Assessment Mapping')
            ->authorize(self::authorized(...))
            ->schema([
                Select::make('assessment_id')
                    ->label('Master Assessment')->required()->rule('integer')->searchable()
                    ->getSearchResultsUsing(fn (ProgramRequirement $record, string $search): array => self::assessments($record, $attach)
                        ->whereLike('title', '%'.$search.'%')->orderBy('title')->limit(25)->get()
                        ->mapWithKeys(fn (MasterAssessment $assessment): array => [$assessment->id => self::label($assessment)])->all())
                    ->getOptionLabelUsing(function (ProgramRequirement $record, $value) use ($attach): ?string {
                        $assessment = self::assessments($record, $attach)->find($value);

                        return $assessment instanceof MasterAssessment ? self::label($assessment) : null;
                    }),
            ])
            ->action(function (ProgramRequirement $record, array $data) use ($attach): void {
                abort_unless(self::authorized($record), 403);

                DB::transaction(function () use ($record, $data, $attach): void {
                    // Serialize mapping changes with mastery finalization for the same assessment.
                    $assessment = self::assessments($record, $attach)->lockForUpdate()->find($data['assessment_id']);
                    $requirement = ProgramRequirement::query()->lockForUpdate()->findOrFail($record->id);
                    abort_unless(self::authorized($requirement), 403);

                    if (! $assessment instanceof MasterAssessment) {
                        throw ValidationException::withMessages(['assessment_id' => 'Select an eligible assessment in this institution.']);
                    }

                    if ($attach) {
                        $requirement->masterAssessments()->syncWithoutDetaching([$assessment->id => ['institution_id' => $requirement->institution_id]]);
                    } else {
                        $requirement->masterAssessments()->detach($assessment->id);
                    }
                }, 3);
            })
            ->successNotificationTitle($attach ? 'Assessment mapped' : 'Assessment mapping removed');
    }

    private static function authorized(ProgramRequirement $record): bool
    {
        $group = $record->programRequirementGroup;

        return ! $record->trashed() && $record->requirement_type === ProgramRequirement::TYPE_COMPETENCY
            && $group !== null && (int) $group->institution_id === (int) $record->institution_id
            && (int) $group->program_id === (int) $record->program_id && ProgramRequirementGroupResource::canEdit($group)
            && in_array(auth()->user()?->activeInstitutionMembership((int) $record->institution_id)?->role, User::INSTITUTION_ADMIN_ROLES, true);
    }

    private static function assessments(ProgramRequirement $record, bool $attach): Builder
    {
        abort_unless(self::authorized($record), 403);

        return MasterAssessment::query()->where('institution_id', $record->institution_id)
            ->whereIn('id', DB::table('competency_assessment_mappings')->select('master_assessment_id')
                ->where('program_requirement_id', $record->id), not: $attach)
            ->with('courseOffering.course');
    }

    private static function label(MasterAssessment $assessment): string
    {
        $section = $assessment->courseOffering;

        return $assessment->title.' · '.($section?->course?->code ?? 'Course').' '.($section?->section_code ?? '');
    }
}

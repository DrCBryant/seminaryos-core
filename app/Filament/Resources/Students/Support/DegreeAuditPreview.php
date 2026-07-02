<?php

namespace App\Filament\Resources\Students\Support;

use App\Models\AcademicRecord;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\ProgramRequirementSubstitution;
use App\Models\Student;
use App\Models\StudentRequirementEvidence;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

class DegreeAuditPreview
{
    /**
     * @var array<int, string>
     */
    protected const COMPLETED_RECORD_STATUSES = ['completed', 'transfer', 'waived'];

    /**
     * @var array<int, string>
     */
    protected const CREDIT_EARNING_RECORD_STATUSES = ['completed', 'transfer'];

    /**
     * @var array<int, string>
     */
    protected const COURSE_MATCH_REQUIREMENT_TYPES = [
        'specific_course',
        'practicum',
        'capstone',
        'field_education',
    ];

    /**
     * @var array<int, string>
     */
    protected const MANUAL_EVIDENCE_REQUIREMENT_TYPES = [
        'non_course_requirement',
        'practicum',
        'capstone',
        'field_education',
        'custom',
    ];

    public static function make(): Action
    {
        return Action::make('viewDegreeAudit')
            ->label('View Degree Audit')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->modalHeading('Degree Audit Preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (Student $record) => view('filament.students.degree-audit-preview', self::getViewData($record)));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getViewData(Student $student): array
    {
        $student->loadMissing([
            'institution',
            'program.programRequirementGroups.programRequirements.course',
            'academicRecords.course',
            'studentRequirementEvidence.programRequirement',
            'programRequirementSubstitutions.substituteCourse',
            'programRequirementSubstitutions.academicRecord.course',
        ]);

        $records = $student->academicRecords
            ->sortBy([
                fn (AcademicRecord $record) => $record->completed_at?->timestamp ?? PHP_INT_MAX,
                fn (AcademicRecord $record) => $record->course_code,
                fn (AcademicRecord $record) => $record->course_title,
            ])
            ->values();

        $evidenceByRequirementId = $student->studentRequirementEvidence
            ->filter(fn (StudentRequirementEvidence $evidence): bool => $evidence->program_requirement_id !== null)
            ->keyBy('program_requirement_id');

        $substitutionsByRequirementId = $student->programRequirementSubstitutions
            ->filter(fn (ProgramRequirementSubstitution $substitution): bool => $substitution->program_requirement_id !== null)
            ->groupBy('program_requirement_id');

        $groups = $student->program?->programRequirementGroups
            ?->where('is_active', true)
            ->sortBy([
                fn (ProgramRequirementGroup $group) => $group->sort_order ?? PHP_INT_MAX,
                fn (ProgramRequirementGroup $group) => $group->name,
            ])
            ->values() ?? collect();

        $requirementResults = collect();
        $groupResults = collect();
        $usedRecordIds = [];
        $remainingCreditsByRecordId = [];

        foreach ($records as $record) {
            $remainingCreditsByRecordId[$record->id] = self::creditsEarned($record);
        }

        foreach ($groups as $group) {
            $groupRequirementResults = collect();

            $requirements = $group->programRequirements
                ->where('is_active', true)
                ->sortBy([
                    fn (ProgramRequirement $requirement) => $requirement->sort_order ?? PHP_INT_MAX,
                    fn (ProgramRequirement $requirement) => $requirement->name,
                ])
                ->values();

            foreach ($requirements as $requirement) {
                $result = self::evaluateRequirement($requirement, $records, $evidenceByRequirementId, $substitutionsByRequirementId, $usedRecordIds, $remainingCreditsByRecordId);

                $groupRequirementResults->push($result);
                $requirementResults->push($result);
            }

            $groupResults->push(self::evaluateGroup($group, $groupRequirementResults));
        }

        $unusedAcademicRecords = $records
            ->filter(fn (AcademicRecord $record): bool => ! in_array($record->id, $usedRecordIds, true))
            ->values();

        $overallAttemptedCredits = (float) $records->sum(fn (AcademicRecord $record): float => (float) ($record->credits_attempted ?? 0));
        $overallEarnedCredits = (float) $records->sum(fn (AcademicRecord $record): float => self::creditsEarned($record));
        $overallGpa = self::calculateOverallGpa($records);

        return [
            'student' => $student,
            'groupResults' => $groupResults,
            'completedRequirements' => $requirementResults->where('status', 'complete')->values(),
            'inProgressRequirements' => $requirementResults->where('status', 'in_progress')->values(),
            'incompleteRequirements' => $requirementResults->where('status', 'incomplete')->values(),
            'notEvaluatedRequirements' => $requirementResults->where('status', 'not_evaluated')->values(),
            'unusedAcademicRecords' => $unusedAcademicRecords,
            'overallAttemptedCredits' => $overallAttemptedCredits,
            'overallEarnedCredits' => $overallEarnedCredits,
            'overallGpa' => $overallGpa,
            'hasProgramRequirements' => $groups->isNotEmpty(),
        ];
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateRequirement(
        ProgramRequirement $requirement,
        Collection $records,
        Collection $evidenceByRequirementId,
        Collection $substitutionsByRequirementId,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        $substitutionResult = self::evaluateRequirementSubstitution(
            $requirement,
            $records,
            $substitutionsByRequirementId,
            $usedRecordIds,
            $remainingCreditsByRecordId,
        );

        if (($substitutionResult['status'] ?? null) === 'complete') {
            return $substitutionResult;
        }

        if ($requirement->course_id !== null && in_array($requirement->requirement_type, self::COURSE_MATCH_REQUIREMENT_TYPES, true)) {
            $courseResult = self::evaluateCourseRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId);

            if ($courseResult['status'] === 'complete') {
                return $courseResult;
            }

            return $substitutionResult['status'] !== 'not_evaluated'
                ? self::preferMoreAdvancedRequirementResult($courseResult, $substitutionResult)
                : $courseResult;
        }

        $baseResult = match ($requirement->requirement_type) {
            'elective_credits' => self::evaluateElectiveCreditsRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
            'transfer_credits' => self::evaluateTransferCreditsRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
            'non_course_requirement', 'practicum', 'capstone', 'field_education', 'custom' => self::evaluateManualRequirement($requirement, $records, $evidenceByRequirementId, $usedRecordIds, $remainingCreditsByRecordId),
            default => self::evaluateManualRequirement($requirement, $records, $evidenceByRequirementId, $usedRecordIds, $remainingCreditsByRecordId),
        };

        return $substitutionResult['status'] !== 'not_evaluated'
            ? self::preferMoreAdvancedRequirementResult($baseResult, $substitutionResult)
            : $baseResult;
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateCourseRequirement(
        ProgramRequirement $requirement,
        Collection $records,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        if ($requirement->course_id === null) {
            return self::baseRequirementResult(
                $requirement,
                'not_evaluated',
                'This requirement is not linked to a course. Manual evidence tracking is not built yet.',
            );
        }

        /** @var AcademicRecord|null $matchedRecord */
        $matchedRecord = $records
            ->first(fn (AcademicRecord $record): bool => self::recordMatchesSpecificCourseRequirement($record, $requirement));

        if ($matchedRecord === null) {
            return self::baseRequirementResult(
                $requirement,
                'incomplete',
                'No completed, transfer, or waived academic record matched this course requirement.',
            );
        }

        $usedRecordIds[] = $matchedRecord->id;
        $usedRecordIds = array_values(array_unique($usedRecordIds));
        $remainingCreditsByRecordId[$matchedRecord->id] = 0.0;

        return array_merge(
            self::baseRequirementResult(
                $requirement,
                'complete',
                $matchedRecord->status === 'waived'
                    ? 'Requirement satisfied by waiver.'
                    : 'Requirement satisfied by matching academic record.',
            ),
            [
                'earnedCredits' => self::creditsEarned($matchedRecord),
                'matchedRecords' => collect([$matchedRecord]),
            ],
        );
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateElectiveCreditsRequirement(
        ProgramRequirement $requirement,
        Collection $records,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        $requiredCredits = (float) ($requirement->required_credits ?? 0);

        if ($requiredCredits <= 0) {
            return self::baseRequirementResult(
                $requirement,
                'not_evaluated',
                'Required credits are not configured for this elective credit requirement.',
            );
        }

        $eligibleRecords = $records
            ->filter(fn (AcademicRecord $record): bool => self::recordEligibleForElectiveCredits($record, $remainingCreditsByRecordId));

        return self::evaluateCreditPoolRequirement(
            $requirement,
            $eligibleRecords,
            $requiredCredits,
            $usedRecordIds,
            $remainingCreditsByRecordId,
            'elective credit requirement',
        );
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateTransferCreditsRequirement(
        ProgramRequirement $requirement,
        Collection $records,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        $requiredCredits = (float) ($requirement->required_credits ?? 0);

        if ($requiredCredits <= 0) {
            return self::baseRequirementResult(
                $requirement,
                'not_evaluated',
                'Required credits are not configured for this transfer credit requirement.',
            );
        }

        $eligibleRecords = $records
            ->filter(fn (AcademicRecord $record): bool => $record->status === 'transfer' && self::recordEligibleForCreditAllocation($record, $remainingCreditsByRecordId));

        return self::evaluateCreditPoolRequirement(
            $requirement,
            $eligibleRecords,
            $requiredCredits,
            $usedRecordIds,
            $remainingCreditsByRecordId,
            'transfer credit requirement',
        );
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateManualRequirement(
        ProgramRequirement $requirement,
        Collection $records,
        Collection $evidenceByRequirementId,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        if ($requirement->course_id !== null) {
            return self::evaluateCourseRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId);
        }

        if (! in_array($requirement->requirement_type, self::MANUAL_EVIDENCE_REQUIREMENT_TYPES, true)) {
            return self::baseRequirementResult(
                $requirement,
                'not_evaluated',
                'This requirement type is not configured for manual evidence evaluation.',
            );
        }

        /** @var StudentRequirementEvidence|null $evidence */
        $evidence = $evidenceByRequirementId->get($requirement->id);

        if ($evidence === null) {
            return self::baseRequirementResult(
                $requirement,
                'incomplete',
                'No approved, waived, pending, or submitted evidence exists for this requirement.',
            );
        }

        return match ($evidence->status) {
            'approved', 'waived' => self::baseRequirementResult(
                $requirement,
                'complete',
                $evidence->status === 'waived'
                    ? 'Requirement satisfied by waived evidence.'
                    : 'Requirement satisfied by approved evidence.',
            ),
            'pending', 'submitted' => self::baseRequirementResult(
                $requirement,
                'in_progress',
                'Requirement evidence has been recorded and is awaiting final approval.',
            ),
            'rejected', 'archived' => self::baseRequirementResult(
                $requirement,
                'incomplete',
                $evidence->status === 'archived'
                    ? 'Requirement evidence exists but is archived and does not satisfy the requirement.'
                    : 'Requirement evidence was rejected and does not satisfy the requirement.',
            ),
            default => self::baseRequirementResult(
                $requirement,
                'incomplete',
                'Requirement evidence does not currently satisfy the requirement.',
            ),
        };

    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateRequirementSubstitution(
        ProgramRequirement $requirement,
        Collection $records,
        Collection $substitutionsByRequirementId,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        /** @var Collection<int, ProgramRequirementSubstitution> $substitutions */
        $substitutions = $substitutionsByRequirementId->get($requirement->id, collect())
            ->sortByDesc(fn (ProgramRequirementSubstitution $substitution): int => match ($substitution->status) {
                'approved' => 4,
                'pending' => 3,
                'rejected' => 2,
                'revoked' => 1,
                'archived' => 0,
                default => -1,
            })
            ->values();

        if ($substitutions->isEmpty()) {
            return self::baseRequirementResult(
                $requirement,
                'not_evaluated',
                'No substitutions are recorded for this requirement.',
            );
        }

        $approvedSubstitution = $substitutions->first(fn (ProgramRequirementSubstitution $substitution): bool => $substitution->status === 'approved');

        if ($approvedSubstitution instanceof ProgramRequirementSubstitution) {
            $matchedRecord = self::resolveSubstitutionAcademicRecord($approvedSubstitution, $records);

            if ($matchedRecord !== null) {
                $usedRecordIds[] = $matchedRecord->id;
                $usedRecordIds = array_values(array_unique($usedRecordIds));
                $remainingCreditsByRecordId[$matchedRecord->id] = 0.0;

                return array_merge(
                    self::baseRequirementResult(
                        $requirement,
                        'complete',
                        'Requirement satisfied by approved substitution.',
                    ),
                    [
                        'earnedCredits' => self::creditsEarned($matchedRecord),
                        'matchedRecords' => collect([$matchedRecord]),
                    ],
                );
            }

            return self::baseRequirementResult(
                $requirement,
                'incomplete',
                'An approved substitution exists, but no eligible academic record was found for it.',
            );
        }

        if ($substitutions->contains(fn (ProgramRequirementSubstitution $substitution): bool => $substitution->status === 'pending')) {
            return self::baseRequirementResult(
                $requirement,
                'in_progress',
                'A substitution is pending approval for this requirement.',
            );
        }

        if ($substitutions->contains(fn (ProgramRequirementSubstitution $substitution): bool => in_array($substitution->status, ['rejected', 'revoked', 'archived'], true))) {
            return self::baseRequirementResult(
                $requirement,
                'incomplete',
                'Only rejected, revoked, or archived substitutions exist for this requirement.',
            );
        }

        return self::baseRequirementResult(
            $requirement,
            'not_evaluated',
            'No substitution state is available for this requirement.',
        );
    }

    protected static function resolveSubstitutionAcademicRecord(
        ProgramRequirementSubstitution $substitution,
        Collection $records,
    ): ?AcademicRecord {
        if ($substitution->academic_record_id !== null) {
            /** @var AcademicRecord|null $academicRecord */
            $academicRecord = $records->first(fn (AcademicRecord $record): bool => (int) $record->id === (int) $substitution->academic_record_id);

            if ($academicRecord !== null && self::recordEligibleForSubstitution($academicRecord)) {
                return $academicRecord;
            }
        }

        if ($substitution->substitute_course_id !== null) {
            /** @var AcademicRecord|null $academicRecord */
            $academicRecord = $records->first(fn (AcademicRecord $record): bool => (int) $record->course_id === (int) $substitution->substitute_course_id && self::recordEligibleForSubstitution($record));

            if ($academicRecord !== null) {
                return $academicRecord;
            }
        }

        return null;
    }

    protected static function recordEligibleForSubstitution(AcademicRecord $record): bool
    {
        if (! in_array((string) $record->status, self::COMPLETED_RECORD_STATUSES, true)) {
            return false;
        }

        if ($record->status === 'waived') {
            return true;
        }

        return $record->earns_credit === true && $record->is_passing === true;
    }

    /**
     * @param  array<string, mixed>  $baseResult
     * @param  array<string, mixed>  $candidateResult
     * @return array<string, mixed>
     */
    protected static function preferMoreAdvancedRequirementResult(array $baseResult, array $candidateResult): array
    {
        $priority = [
            'complete' => 3,
            'in_progress' => 2,
            'incomplete' => 1,
            'not_evaluated' => 0,
        ];

        return ($priority[$candidateResult['status']] ?? -1) > ($priority[$baseResult['status']] ?? -1)
            ? $candidateResult
            : $baseResult;
    }

    /**
     * @param  array<int, int>  $usedRecordIds
     * @param  array<int, float>  $remainingCreditsByRecordId
     * @return array<string, mixed>
     */
    protected static function evaluateCreditPoolRequirement(
        ProgramRequirement $requirement,
        Collection $eligibleRecords,
        float $requiredCredits,
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
        string $requirementLabel,
    ): array {
        $earnedCredits = 0.0;
        $matchedRecords = collect();

        foreach ($eligibleRecords as $record) {
            $availableCredits = $remainingCreditsByRecordId[$record->id] ?? 0.0;

            if ($availableCredits <= 0) {
                continue;
            }

            $creditsNeeded = max($requiredCredits - $earnedCredits, 0);
            $allocatedCredits = min($availableCredits, $creditsNeeded);

            if ($allocatedCredits <= 0) {
                continue;
            }

            $earnedCredits += $allocatedCredits;
            $remainingCreditsByRecordId[$record->id] = max($availableCredits - $allocatedCredits, 0);
            $usedRecordIds[] = $record->id;
            $matchedRecords->push([
                'record' => $record,
                'appliedCredits' => $allocatedCredits,
            ]);

            if ($earnedCredits >= $requiredCredits) {
                break;
            }
        }

        $usedRecordIds = array_values(array_unique($usedRecordIds));

        $status = match (true) {
            $earnedCredits >= $requiredCredits => 'complete',
            $earnedCredits > 0 => 'in_progress',
            default => 'incomplete',
        };

        $message = match ($status) {
            'complete' => 'Sufficient credits were applied to this '.$requirementLabel.'.',
            'in_progress' => 'Some credits were applied, but more are still required for this '.$requirementLabel.'.',
            default => 'No eligible credits are currently available for this '.$requirementLabel.'.',
        };

        return array_merge(
            self::baseRequirementResult($requirement, $status, $message),
            [
                'earnedCredits' => $earnedCredits,
                'matchedRecords' => $matchedRecords,
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requirementResults
     * @return array<string, mixed>
     */
    protected static function evaluateGroup(ProgramRequirementGroup $group, Collection $requirementResults): array
    {
        $requiredCredits = $group->required_credits !== null ? (float) $group->required_credits : null;
        $minimumGpa = $group->minimum_gpa !== null ? (float) $group->minimum_gpa : null;
        $earnedCredits = (float) $requirementResults->sum(fn (array $result): float => (float) ($result['earnedCredits'] ?? 0));

        $groupGpa = self::calculateOverallGpaFromRequirementResults($requirementResults);

        $creditRequirementMet = $requiredCredits === null || $earnedCredits >= $requiredCredits;
        $gpaRequirementMet = $minimumGpa === null || ($groupGpa !== null && $groupGpa >= $minimumGpa);
        $hasNotEvaluated = $requirementResults->contains(fn (array $result): bool => $result['status'] === 'not_evaluated');
        $hasInProgress = $requirementResults->contains(fn (array $result): bool => $result['status'] === 'in_progress');
        $hasIncomplete = $requirementResults->contains(fn (array $result): bool => $result['status'] === 'incomplete');
        $allComplete = $requirementResults->isNotEmpty() && $requirementResults->every(fn (array $result): bool => $result['status'] === 'complete');

        $status = match (true) {
            $requirementResults->isEmpty() => 'not_evaluated',
            $allComplete && $creditRequirementMet && $gpaRequirementMet => 'complete',
            $hasInProgress || ($earnedCredits > 0 && ! $creditRequirementMet) => 'in_progress',
            $hasIncomplete => 'incomplete',
            $hasNotEvaluated && $earnedCredits <= 0 => 'not_evaluated',
            $hasNotEvaluated => 'in_progress',
            default => 'incomplete',
        };

        if ($minimumGpa !== null && $groupGpa === null) {
            $status = $earnedCredits > 0 ? 'in_progress' : 'not_evaluated';
        }

        return [
            'group' => $group,
            'requirements' => $requirementResults,
            'earnedCredits' => $earnedCredits,
            'requiredCredits' => $requiredCredits,
            'minimumGpa' => $minimumGpa,
            'calculatedGpa' => $groupGpa,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function baseRequirementResult(ProgramRequirement $requirement, string $status, string $message): array
    {
        return [
            'requirement' => $requirement,
            'status' => $status,
            'message' => $message,
            'earnedCredits' => 0.0,
            'matchedRecords' => collect(),
        ];
    }

    protected static function recordMatchesSpecificCourseRequirement(AcademicRecord $record, ProgramRequirement $requirement): bool
    {
        if ((int) $record->course_id !== (int) $requirement->course_id) {
            return false;
        }

        if (! in_array((string) $record->status, self::COMPLETED_RECORD_STATUSES, true)) {
            return false;
        }

        if ($record->status === 'waived') {
            return true;
        }

        return $record->earns_credit === true && $record->is_passing === true;
    }

    /**
     * @param  array<int, float>  $remainingCreditsByRecordId
     */
    protected static function recordEligibleForElectiveCredits(AcademicRecord $record, array $remainingCreditsByRecordId): bool
    {
        return in_array((string) $record->status, self::CREDIT_EARNING_RECORD_STATUSES, true)
            && $record->earns_credit === true
            && self::recordEligibleForCreditAllocation($record, $remainingCreditsByRecordId);
    }

    /**
     * @param  array<int, float>  $remainingCreditsByRecordId
     */
    protected static function recordEligibleForCreditAllocation(AcademicRecord $record, array $remainingCreditsByRecordId): bool
    {
        return ($remainingCreditsByRecordId[$record->id] ?? 0.0) > 0;
    }

    protected static function creditsEarned(AcademicRecord $record): float
    {
        return (float) ($record->credits_earned ?? 0);
    }

    protected static function calculateOverallGpa(Collection $records): ?float
    {
        $gpaRecords = $records->filter(fn (AcademicRecord $record): bool => self::isCompleteGpaBearingRecord($record));
        $credits = (float) $gpaRecords->sum(fn (AcademicRecord $record): float => (float) ($record->credits_attempted ?? 0));

        if ($credits <= 0) {
            return null;
        }

        $qualityPoints = (float) $gpaRecords->sum(fn (AcademicRecord $record): float => (float) $record->credits_attempted * (float) $record->grade_points);

        return $qualityPoints / $credits;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requirementResults
     */
    protected static function calculateOverallGpaFromRequirementResults(Collection $requirementResults): ?float
    {
        $records = $requirementResults
            ->flatMap(function (array $result): Collection {
                return collect($result['matchedRecords'] ?? [])->map(function (mixed $entry): ?AcademicRecord {
                    if ($entry instanceof AcademicRecord) {
                        return $entry;
                    }

                    if (is_array($entry) && ($entry['record'] ?? null) instanceof AcademicRecord) {
                        return $entry['record'];
                    }

                    return null;
                })->filter();
            })
            ->unique(fn (AcademicRecord $record): int => $record->id)
            ->values();

        return self::calculateOverallGpa($records);
    }

    protected static function isCompleteGpaBearingRecord(AcademicRecord $record): bool
    {
        return $record->affects_gpa === true
            && $record->credits_attempted !== null
            && (float) $record->credits_attempted > 0
            && $record->grade_points !== null;
    }
}

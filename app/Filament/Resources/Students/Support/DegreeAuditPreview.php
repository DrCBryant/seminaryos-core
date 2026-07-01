<?php

namespace App\Filament\Resources\Students\Support;

use App\Models\AcademicRecord;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementGroup;
use App\Models\Student;
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
        ]);

        $records = $student->academicRecords
            ->sortBy([
                fn (AcademicRecord $record) => $record->completed_at?->timestamp ?? PHP_INT_MAX,
                fn (AcademicRecord $record) => $record->course_code,
                fn (AcademicRecord $record) => $record->course_title,
            ])
            ->values();

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
                $result = self::evaluateRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId);

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
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        if (in_array($requirement->requirement_type, self::COURSE_MATCH_REQUIREMENT_TYPES, true)) {
            return self::evaluateCourseRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId);
        }

        return match ($requirement->requirement_type) {
            'elective_credits' => self::evaluateElectiveCreditsRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
            'transfer_credits' => self::evaluateTransferCreditsRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
            'non_course_requirement', 'custom' => self::evaluateManualRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
            default => self::evaluateManualRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId),
        };
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
        array &$usedRecordIds,
        array &$remainingCreditsByRecordId,
    ): array {
        if ($requirement->course_id !== null) {
            return self::evaluateCourseRequirement($requirement, $records, $usedRecordIds, $remainingCreditsByRecordId);
        }

        return self::baseRequirementResult(
            $requirement,
            'not_evaluated',
            'Manual evidence tracking is not built yet for this requirement type.',
        );
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

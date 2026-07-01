<?php

namespace App\Filament\Resources\Students\Support;

use App\Models\AcademicRecord;
use App\Models\Student;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

class GpaPreview
{
    public static function make(): Action
    {
        return Action::make('viewGpaPreview')
            ->label('View GPA Preview')
            ->icon('heroicon-o-calculator')
            ->color('gray')
            ->modalHeading('GPA Preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (Student $record) => view('filament.students.gpa-preview', self::getViewData($record)));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getViewData(Student $student): array
    {
        $student->loadMissing([
            'institution',
            'program',
            'academicRecords.academicTerm',
            'academicRecords.gradeScale',
            'academicRecords.gradeValue',
        ]);

        $records = $student->academicRecords
            ->sortBy([
                fn (AcademicRecord $record) => $record->academicTerm?->start_date?->timestamp ?? PHP_INT_MAX,
                fn (AcademicRecord $record) => $record->course_code,
                fn (AcademicRecord $record) => $record->course_title,
            ])
            ->values();

        $includedRecords = $records
            ->filter(fn (AcademicRecord $record): bool => $record->affects_gpa === true)
            ->values();

        $excludedRecords = $records
            ->filter(fn (AcademicRecord $record): bool => $record->affects_gpa !== true)
            ->map(function (AcademicRecord $record): array {
                $reason = match (true) {
                    $record->affects_gpa === false => 'Excluded by grade scale configuration',
                    $record->affects_gpa === null && $record->gradeValue !== null => 'Grade value metadata does not mark this record as GPA-bearing',
                    $record->affects_gpa === null && $record->final_grade === null => 'No final grade metadata is available',
                    default => 'No GPA-affecting metadata is set',
                };

                return [
                    'record' => $record,
                    'reason' => $reason,
                ];
            })
            ->values();

        $termGroups = $includedRecords
            ->filter(fn (AcademicRecord $record) => $record->academicTerm !== null)
            ->groupBy(fn (AcademicRecord $record) => (string) $record->academic_term_id)
            ->map(function (Collection $group): array {
                /** @var AcademicRecord $firstRecord */
                $firstRecord = $group->first();
                $term = $firstRecord->academicTerm;
                $termCredits = (float) $group->sum(fn (AcademicRecord $record) => (float) ($record->credits_attempted ?? 0));
                $termQualityPoints = (float) $group->sum(fn (AcademicRecord $record) => self::calculateQualityPoints($record));

                return [
                    'label' => $term ? "{$term->name} ({$term->academic_year})" : 'Academic Term',
                    'records' => $group,
                    'gpaCredits' => $termCredits,
                    'qualityPoints' => $termQualityPoints,
                    'gpa' => $termCredits > 0 ? $termQualityPoints / $termCredits : null,
                ];
            })
            ->values();

        $otherIncludedRecords = $includedRecords
            ->filter(fn (AcademicRecord $record) => $record->academicTerm === null)
            ->values();

        $totalGpaCredits = (float) $includedRecords->sum(fn (AcademicRecord $record) => (float) ($record->credits_attempted ?? 0));
        $totalQualityPoints = (float) $includedRecords->sum(fn (AcademicRecord $record) => self::calculateQualityPoints($record));

        return [
            'student' => $student,
            'termGroups' => $termGroups,
            'otherIncludedRecords' => $otherIncludedRecords,
            'excludedRecords' => $excludedRecords,
            'totalGpaCredits' => $totalGpaCredits,
            'totalQualityPoints' => $totalQualityPoints,
            'overallGpa' => $totalGpaCredits > 0 ? $totalQualityPoints / $totalGpaCredits : null,
        ];
    }

    protected static function calculateQualityPoints(AcademicRecord $record): float
    {
        $creditsAttempted = (float) ($record->credits_attempted ?? 0);
        $gradePoints = (float) ($record->grade_points ?? 0);

        return $creditsAttempted * $gradePoints;
    }
}

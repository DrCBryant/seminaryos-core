<?php

namespace App\Filament\Resources\Students\Support;

use App\Models\Student;
use App\Support\AcademicTerms\ReportingTermResolver;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

class TranscriptPreview
{
    public static function make(): Action
    {
        return Action::make('viewTranscriptPreview')
            ->label('View Transcript Preview')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading('Transcript Preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (Student $record) => view('filament.students.transcript-preview', self::getViewData($record)));
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
            'academicRecords.course',
        ]);

        $records = $student->academicRecords
            ->sortBy([
                fn ($record) => $record->academicTerm?->start_date?->timestamp ?? PHP_INT_MAX,
                fn ($record) => $record->course_code,
                fn ($record) => $record->course_title,
            ])
            ->values();

        $resolver = app(ReportingTermResolver::class);
        $reportingTerms = $records->mapWithKeys(function ($record) use ($resolver): array {
            $resolution = $resolver->resolveAcademicRecord($record);

            return [$record->id => $resolution];
        });

        $termGroups = $records
            ->filter(fn ($record): bool => $reportingTerms[$record->id]['term'] !== null || $record->course?->isCompletionDateGrouped())
            ->groupBy(fn ($record) => (string) ($reportingTerms[$record->id]['term']?->id ?? 'unresolved-'.$reportingTerms[$record->id]['status']))
            ->map(function (Collection $group) use ($reportingTerms) {
                $resolution = $reportingTerms[$group->first()->id];
                $term = $resolution['term'];

                return [
                    'label' => $term?->display_label ?? ($resolution['message'] ?? 'Reporting term unresolved'),
                    'records' => $group,
                ];
            })
            ->values();

        $otherGroups = $records
            ->filter(fn ($record): bool => $reportingTerms[$record->id]['term'] === null && ! $record->course?->isCompletionDateGrouped())
            ->groupBy(fn ($record): string => in_array($record->status, ['transfer', 'waived'], true) ? $record->status : 'no_term')
            ->map(function (Collection $group, string $key): array {
                return [
                    'label' => match ($key) {
                        'transfer' => 'Transfer Records',
                        'waived' => 'Waived Records',
                        default => 'Records Without Academic Term',
                    },
                    'records' => $group,
                ];
            })
            ->values();

        return [
            'student' => $student,
            'termGroups' => $termGroups,
            'otherGroups' => $otherGroups,
            'totalCreditsAttempted' => (float) $records->sum(fn ($record) => (float) ($record->credits_attempted ?? 0)),
            'totalCreditsEarned' => (float) $records->sum(fn ($record) => (float) ($record->credits_earned ?? 0)),
        ];
    }
}

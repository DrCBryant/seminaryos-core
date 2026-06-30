<?php

namespace App\Filament\Resources\Students\Support;

use App\Models\Student;
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
        ]);

        $records = $student->academicRecords
            ->sortBy([
                fn ($record) => $record->academicTerm?->start_date?->timestamp ?? PHP_INT_MAX,
                fn ($record) => $record->course_code,
                fn ($record) => $record->course_title,
            ])
            ->values();

        $termGroups = $records
            ->filter(fn ($record) => $record->academicTerm !== null)
            ->groupBy(fn ($record) => (string) $record->academic_term_id)
            ->map(function (Collection $group) {
                $term = $group->first()->academicTerm;

                return [
                    'label' => $term ? "{$term->name} ({$term->academic_year})" : 'Academic Term',
                    'records' => $group,
                ];
            })
            ->values();

        $otherGroups = $records
            ->filter(fn ($record) => $record->academicTerm === null)
            ->groupBy(function ($record): string {
                return in_array($record->status, ['transfer', 'waived'], true)
                    ? $record->status
                    : 'no_term';
            })
            ->map(function (Collection $group, string $key) {
                $label = match ($key) {
                    'transfer' => 'Transfer Records',
                    'waived' => 'Waived Records',
                    default => 'Records Without Academic Term',
                };

                return [
                    'label' => $label,
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

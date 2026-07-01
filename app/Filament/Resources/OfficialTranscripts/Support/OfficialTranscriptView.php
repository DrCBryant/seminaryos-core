<?php

namespace App\Filament\Resources\OfficialTranscripts\Support;

use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

class OfficialTranscriptView
{
    public static function make(): Action
    {
        return Action::make('viewOfficialTranscript')
            ->label('View Official Transcript')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (OfficialTranscript $record): bool => $record->status === 'issued')
            ->modalHeading('Official Transcript')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (OfficialTranscript $record) => view('filament.official-transcripts.view', self::getViewData($record)));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getViewData(OfficialTranscript $transcript): array
    {
        $transcript->loadMissing([
            'institution',
            'student.program',
            'lines',
        ]);

        $lines = $transcript->lines
            ->sortBy([
                fn (OfficialTranscriptLine $line) => $line->sort_order ?? PHP_INT_MAX,
                fn (OfficialTranscriptLine $line) => $line->course_code,
                fn (OfficialTranscriptLine $line) => $line->course_title,
            ])
            ->values();

        $termGroups = $lines
            ->filter(fn (OfficialTranscriptLine $line) => filled($line->term_label))
            ->groupBy(fn (OfficialTranscriptLine $line) => (string) $line->term_label)
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'lines' => $group,
            ])
            ->values();

        $otherGroups = $lines
            ->filter(fn (OfficialTranscriptLine $line) => blank($line->term_label))
            ->groupBy(function (OfficialTranscriptLine $line): string {
                return match ($line->status) {
                    'transfer' => 'transfer',
                    'waived' => 'waived',
                    default => 'other',
                };
            })
            ->map(function (Collection $group, string $key) {
                $label = match ($key) {
                    'transfer' => 'Transfer Records',
                    'waived' => 'Waived Records',
                    default => 'Other Records',
                };

                return [
                    'label' => $label,
                    'lines' => $group,
                ];
            })
            ->values();

        return [
            'transcript' => $transcript,
            'student' => $transcript->student,
            'termGroups' => $termGroups,
            'otherGroups' => $otherGroups,
            'totalCreditsAttempted' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_attempted ?? 0)),
            'totalCreditsEarned' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_earned ?? 0)),
            'pdfFilename' => 'official-transcript-'.str($transcript->transcript_number)->slug()->value().'.pdf',
        ];
    }
}

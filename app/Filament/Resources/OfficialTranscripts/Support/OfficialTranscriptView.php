<?php

namespace App\Filament\Resources\OfficialTranscripts\Support;

use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\TranscriptSetting;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

class OfficialTranscriptView
{
    protected const DEFAULT_SETTINGS = [
        'transcript_title' => 'Official Transcript',
        'certification_statement' => 'This official transcript is certified by the registrar as a true and complete academic record as of the issue date shown.',
        'footer_statement' => 'End of official transcript.',
        'grading_scale_note' => 'Grade points and GPA calculations are not included unless specifically enabled by institutional transcript settings.',
        'accreditation_note' => 'Accreditation information is available from the issuing institution upon request.',
        'transcript_disclaimer' => 'This transcript is intended for official academic use and reflects information available at the time of issue.',
        'show_recipient_info' => true,
        'show_delivery_method' => true,
        'show_purpose' => true,
        'show_grade_points' => false,
        'show_status' => true,
    ];

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
            'institution.activeTranscriptSetting',
            'student.program',
            'lines',
        ]);

        /** @var TranscriptSetting|null $activeTranscriptSetting */
        $activeTranscriptSetting = $transcript->institution?->activeTranscriptSetting;

        $transcriptSettings = array_merge(
            self::DEFAULT_SETTINGS,
            $activeTranscriptSetting?->only([
                'transcript_title',
                'registrar_name',
                'registrar_title',
                'certification_statement',
                'footer_statement',
                'grading_scale_note',
                'accreditation_note',
                'transcript_disclaimer',
                'show_recipient_info',
                'show_delivery_method',
                'show_purpose',
                'show_grade_points',
                'show_status',
            ]) ?? [],
        );

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
            'transcriptSettings' => $transcriptSettings,
            'termGroups' => $termGroups,
            'otherGroups' => $otherGroups,
            'totalCreditsAttempted' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_attempted ?? 0)),
            'totalCreditsEarned' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_earned ?? 0)),
            'pdfFilename' => 'official-transcript-'.str($transcript->transcript_number)->slug()->value().'.pdf',
        ];
    }
}

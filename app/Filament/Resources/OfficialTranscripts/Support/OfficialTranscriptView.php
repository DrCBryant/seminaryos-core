<?php

namespace App\Filament\Resources\OfficialTranscripts\Support;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use App\Filament\Resources\Students\Support\GpaPreview;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\Program;
use App\Models\Student;
use Filament\Actions\Action;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class OfficialTranscriptView
{
    protected const DEFAULT_SETTINGS = [
        'transcript_title' => 'Official Transcript',
        'registrar_name' => null,
        'registrar_title' => null,
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
            ->authorize(fn (OfficialTranscript $record): bool => self::canView($record))
            ->modalHeading('Official Transcript')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (OfficialTranscript $record) => view('filament.official-transcripts.view', self::getViewData($record)));
    }

    public function print(string $transcript): Response|RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        $record = OfficialTranscript::query()->where('uuid', $transcript)
            ->where('institution_id', auth()->user()->current_institution_id)
            ->where('status', 'issued')->firstOrFail();
        abort_unless(self::canView($record), 403);

        $data = self::getViewData($record);
        $number = str($record->transcript_number)->squish()->limit(44)->value();
        $studentNumber = str($data['document']['student_number'] ?? '')->squish()->limit(25)->value();
        $identity = ($data['legacy'] ? 'Legacy transcript ' : 'Transcript ').$number
            .($studentNumber === '' ? '' : ' · Student '.$studentNumber);

        // A CSS string made entirely of Unicode escapes cannot break out of the style element.
        $data['pageIdentity'] = '"'.implode('', array_map(
            fn (string $character): string => '\\'.dechex(mb_ord($character)).' ',
            mb_str_split($identity),
        )).'"';

        return response()->view('filament.official-transcripts.print', $data, 200, [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
        ]);
    }

    private static function canView(OfficialTranscript $transcript): bool
    {
        $actor = auth()->user();

        return $actor !== null && $actor->status === 'active' && $actor->deleted_at === null
            && (int) $actor->current_institution_id === (int) $transcript->institution_id
            && ! $transcript->trashed()
            && $actor->institutions()->whereKey($transcript->institution_id)->wherePivot('status', 'active')->exists()
            && OfficialTranscriptResource::canView($transcript);
    }

    /**
     * @param  Collection<int, OfficialTranscriptLine>  $lines
     * @return array<string, mixed>
     */
    public static function captureSnapshot(Institution $institution, Student $student, ?Program $program, string $deliveryMethod, Collection $lines): array
    {
        $configuredSettings = $institution->activeTranscriptSetting?->only(array_keys(self::DEFAULT_SETTINGS)) ?? [];
        $settings = array_replace(self::DEFAULT_SETTINGS, $configuredSettings);
        $settings['show_registrar_footer'] = filled($settings['transcript_disclaimer']) || filled($settings['footer_statement'])
            || filled($settings['registrar_name']) || filled($settings['registrar_title']);
        $settings['transcript_title'] ??= self::DEFAULT_SETTINGS['transcript_title'];
        $settings['registrar_name'] ??= 'Registrar';
        $settings['registrar_title'] ??= 'Office of the Registrar';

        foreach (self::DEFAULT_SETTINGS as $key => $default) {
            if (is_bool($default)) {
                $settings[$key] = (bool) ($settings[$key] ?? $default);
            }
        }

        $termGpa = $lines->whereNotNull('term_snapshot_id')->groupBy('term_snapshot_id')
            ->map(fn (Collection $termLines): array => GpaPreview::summarize($termLines))->all();

        return [
            'version' => 1,
            'institution_name' => $institution->name,
            'student_name' => $student->full_name,
            'student_number' => $student->student_number,
            'enrollment_date' => $student->enrollment_date?->toDateString(),
            'program' => $program?->only(['code', 'title', 'credential_type']),
            'delivery_label' => OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS[$deliveryMethod] ?? $deliveryMethod,
            'settings' => $settings,
            'gpa' => [
                'cumulative' => GpaPreview::summarize($lines),
                'terms' => $termGpa,
            ],
            'awards' => [],
        ];
    }

    /** @return array<string, mixed> */
    protected static function getViewData(OfficialTranscript $transcript): array
    {
        $snapshot = $transcript->document_snapshot;
        $legacy = $snapshot === null;
        $lines = $transcript->lines()->where('institution_id', $transcript->institution_id)
            ->where('student_id', $transcript->student_id)->get();
        $termGroups = $lines
            ->filter(fn (OfficialTranscriptLine $line) => filled($line->term_label))
            ->groupBy(function (OfficialTranscriptLine $line) use ($legacy): string {
                $termId = $legacy ? $line->academic_term_id : $line->term_snapshot_id;

                return $termId === null ? 'label:'.$line->term_label : 'term:'.$termId;
            })
            ->map(fn (Collection $group): array => [
                'label' => $group->first()->term_label,
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
            'legacy' => $legacy,
            'document' => $snapshot,
            'transcriptSettings' => $snapshot['settings'] ?? [
                'show_recipient_info' => false,
                'show_delivery_method' => false,
                'show_purpose' => false,
                'show_grade_points' => false,
                'show_status' => false,
            ],
            'termGroups' => $termGroups,
            'otherGroups' => $otherGroups,
            'totalCreditsAttempted' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_attempted ?? 0)),
            'totalCreditsEarned' => (float) $lines->sum(fn (OfficialTranscriptLine $line) => (float) ($line->credits_earned ?? 0)),
            'pdfFilename' => 'official-transcript-'.str($transcript->transcript_number)->slug()->value().'.pdf',
        ];
    }
}

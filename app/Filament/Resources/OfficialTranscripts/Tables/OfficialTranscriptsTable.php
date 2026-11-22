<?php

namespace App\Filament\Resources\OfficialTranscripts\Tables;

use App\Core\Scopes\InstitutionScope;
use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use App\Filament\Resources\OfficialTranscripts\Support\OfficialTranscriptView;
use App\Models\AcademicRecord;
use App\Models\OfficialTranscript;
use App\Support\AcademicTerms\ReportingTermResolver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfficialTranscriptsTable
{
    protected const ISSUABLE_STATUSES = ['draft', 'requested', 'under_review'];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('transcript_number')
                    ->label('Transcript number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OfficialTranscript::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'voided' => 'danger',
                        'under_review' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('purpose')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('requested_at')
                    ->label('Requested date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Issued date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivery_method')
                    ->label('Delivery method')
                    ->formatStateUsing(fn (?string $state): string => $state ? (OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS[$state] ?? $state) : '—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OfficialTranscript::statusOptions()),
                SelectFilter::make('delivery_method')
                    ->options(OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS),
                Filter::make('requested_at')
                    ->label('Requested date')
                    ->query(fn ($query) => $query->whereNotNull('requested_at')),
                Filter::make('issued_at')
                    ->label('Issued date')
                    ->query(fn ($query) => $query->whereNotNull('issued_at')),
                Filter::make('issued_without_lines')
                    ->label('Issued without snapshot lines')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'issued')->whereDoesntHave('lines')),
                Filter::make('issued_without_document_snapshot')
                    ->label('Legacy issued transcripts')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'issued')->whereNull('document_snapshot')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                OfficialTranscriptView::make(),
                Action::make('issueTranscript')
                    ->label('Issue Transcript')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize(fn (OfficialTranscript $record): bool => OfficialTranscriptResource::canEdit($record))
                    ->visible(fn (OfficialTranscript $record): bool => in_array($record->status, self::ISSUABLE_STATUSES, true))
                    ->modalHeading('Issue official transcript')
                    ->modalDescription('This will mark the transcript as issued without generating a PDF or sending email.')
                    ->form([
                        TextInput::make('transcript_number')
                            ->label('Transcript number')
                            ->default(fn (OfficialTranscript $record): ?string => $record->transcript_number)
                            ->maxLength(255),
                        TextInput::make('purpose')
                            ->default(fn (OfficialTranscript $record): ?string => $record->purpose)
                            ->maxLength(255),
                        TextInput::make('recipient_name')
                            ->default(fn (OfficialTranscript $record): ?string => $record->recipient_name)
                            ->maxLength(255),
                        TextInput::make('recipient_email')
                            ->email()
                            ->default(fn (OfficialTranscript $record): ?string => $record->recipient_email)
                            ->maxLength(255),
                        Select::make('delivery_method')
                            ->options(OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS)
                            ->default(fn (OfficialTranscript $record): ?string => $record->delivery_method)
                            ->required(),
                        DateTimePicker::make('issued_at')
                            ->label('Issued date')
                            ->default(Carbon::now())
                            ->seconds(false)
                            ->required(),
                        Textarea::make('registrar_notes')
                            ->default(fn (OfficialTranscript $record): ?string => $record->registrar_notes)
                            ->rows(4),
                    ])
                    ->action(function (OfficialTranscript $record, array $data): void {
                        if (! self::issue($record->getKey(), $data)) {
                            return;
                        }

                        Notification::make()
                            ->title('Official transcript issued')
                            ->body('The official transcript was marked as issued successfully.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /** @param array<string, mixed> $data */
    protected static function issue(int $transcriptId, array $data): bool
    {
        return DB::transaction(function () use ($transcriptId, $data): bool {
            $record = OfficialTranscript::query()->lockForUpdate()->findOrFail($transcriptId);
            abort_unless(auth()->check() && OfficialTranscriptResource::canEdit($record), 403);

            if ($record->document_snapshot !== null || ! in_array($record->status, self::ISSUABLE_STATUSES, true)) {
                Notification::make()
                    ->title('Transcript cannot be issued')
                    ->body('The transcript is no longer eligible for issuance.')
                    ->warning()
                    ->send();

                return false;
            }

            $student = $record->student()->where('institution_id', $record->institution_id)->first();
            $institution = $record->institution()->first();
            $program = $student?->program()->withoutGlobalScope(InstitutionScope::class)->withTrashed()->first();

            if (! $institution || ($program && $program->institution_id !== $record->institution_id)) {
                Notification::make()
                    ->title('Transcript cannot be issued')
                    ->body('An issuing institution and institution-consistent program information are required.')
                    ->warning()
                    ->send();

                return false;
            }

            $academicRecords = $student?->academicRecords()
                ->where('institution_id', $record->institution_id)
                ->with([
                    'academicTerm' => fn ($query) => $query->withoutGlobalScope(InstitutionScope::class)->withTrashed(),
                    'course' => fn ($query) => $query->withoutGlobalScope(InstitutionScope::class)->withTrashed(),
                ])
                ->get();

            if ($academicRecords === null || $academicRecords->isEmpty()) {
                Notification::make()
                    ->title('Transcript cannot be issued')
                    ->body('The student has no academic records, so this official transcript cannot be issued yet.')
                    ->warning()
                    ->send();

                return false;
            }

            $resolver = app(ReportingTermResolver::class);
            $reportingTerms = [];

            foreach ($academicRecords as $academicRecord) {
                if (($academicRecord->course && $academicRecord->course->institution_id !== $record->institution_id)
                    || ($academicRecord->academicTerm && $academicRecord->academicTerm->institution_id !== $record->institution_id)) {
                    Notification::make()
                        ->title('Transcript cannot be issued')
                        ->body('An academic record refers to data from another institution.')
                        ->warning()
                        ->send();

                    return false;
                }

                $resolution = $resolver->resolveAcademicRecord($academicRecord);

                if ($resolution['status'] !== ReportingTermResolver::RESOLVED) {
                    Notification::make()
                        ->title('Transcript cannot be issued')
                        ->body($resolution['message'] ?? 'A reporting term requires registrar resolution before issuance.')
                        ->warning()
                        ->send();

                    return false;
                }

                $reportingTerms[$academicRecord->id] = $resolution['term'];
            }

            $transcriptNumber = filled($data['transcript_number'] ?? null)
                ? trim((string) $data['transcript_number'])
                : self::generateTranscriptNumber($record);

            $duplicateExists = OfficialTranscript::query()
                ->withTrashed()
                ->where('institution_id', $record->institution_id)
                ->where('transcript_number', $transcriptNumber)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($duplicateExists) {
                Notification::make()
                    ->title('Transcript number already exists')
                    ->body('Another official transcript already uses this transcript number in the same institution.')
                    ->warning()
                    ->send();

                return false;
            }

            $record->lines()->delete();

            $sortOrder = 1;

            $lines = $record->lines()->createMany(
                $academicRecords
                    ->sortBy([
                        fn (AcademicRecord $left, AcademicRecord $right): int => [
                            $reportingTerms[$left->id]?->start_date?->timestamp ?? PHP_INT_MAX,
                            $reportingTerms[$left->id]?->id ?? PHP_INT_MAX,
                        ] <=> [
                            $reportingTerms[$right->id]?->start_date?->timestamp ?? PHP_INT_MAX,
                            $reportingTerms[$right->id]?->id ?? PHP_INT_MAX,
                        ],
                        ['course_code', 'asc'],
                        ['course_title', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values()
                    ->map(function (AcademicRecord $academicRecord) use (&$sortOrder, $record, $reportingTerms): array {
                        $term = $reportingTerms[$academicRecord->id];

                        return [
                            'institution_id' => $record->institution_id,
                            'academic_record_id' => $academicRecord->id,
                            'student_id' => $academicRecord->student_id,
                            'academic_term_id' => $term?->id,
                            'term_label' => $term?->display_label,
                            'term_snapshot_id' => $term?->id,
                            'course_code' => $academicRecord->course_code,
                            'course_title' => $academicRecord->course_title,
                            'credits_attempted' => $academicRecord->credits_attempted,
                            'credits_earned' => $academicRecord->credits_earned,
                            'final_grade' => $academicRecord->final_grade,
                            'grade_label' => $academicRecord->grade_label,
                            'grade_points' => $academicRecord->grade_points,
                            'affects_gpa' => $academicRecord->affects_gpa,
                            'status' => $academicRecord->status,
                            'completed_at' => $academicRecord->completed_at,
                            'sort_order' => $sortOrder++,
                            'notes' => $academicRecord->notes,
                        ];
                    })
                    ->all(),
            );

            if ($lines->contains(fn ($line): bool => ! $line->exists)) {
                throw new RuntimeException('Could not save every transcript snapshot line.');
            }

            $record->forceFill([
                'transcript_number' => $transcriptNumber,
                'status' => 'issued',
                'purpose' => ($data['purpose'] ?? null) ?: null,
                'issued_at' => $data['issued_at'],
                'recipient_name' => ($data['recipient_name'] ?? null) ?: null,
                'recipient_email' => ($data['recipient_email'] ?? null) ?: null,
                'delivery_method' => $data['delivery_method'],
                'registrar_notes' => ($data['registrar_notes'] ?? null) ?: null,
                'document_snapshot' => OfficialTranscriptView::captureSnapshot($institution, $student, $program, $data['delivery_method'], $lines),
            ]);

            if (! $record->save()) {
                throw new RuntimeException('Could not save the issued transcript document snapshot.');
            }

            return true;
        }, 3);
    }

    protected static function generateTranscriptNumber(OfficialTranscript $record): string
    {
        $dateSegment = now()->format('Ymd');

        do {
            $candidate = sprintf(
                'OT-%d-%s-%04d',
                $record->institution_id,
                $dateSegment,
                random_int(1, 9999),
            );
        } while (OfficialTranscript::query()
            ->withTrashed()
            ->where('institution_id', $record->institution_id)
            ->where('transcript_number', $candidate)
            ->exists());

        return $candidate;
    }
}

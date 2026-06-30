<?php

namespace App\Filament\Resources\OfficialTranscripts\Tables;

use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use App\Models\OfficialTranscript;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
                    ->formatStateUsing(fn (string $state): string => OfficialTranscriptForm::STATUS_OPTIONS[$state] ?? $state)
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
                    ->options(OfficialTranscriptForm::STATUS_OPTIONS),
                SelectFilter::make('delivery_method')
                    ->options(OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS),
                Filter::make('requested_at')
                    ->label('Requested date')
                    ->query(fn ($query) => $query->whereNotNull('requested_at')),
                Filter::make('issued_at')
                    ->label('Issued date')
                    ->query(fn ($query) => $query->whereNotNull('issued_at')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('issueTranscript')
                    ->label('Issue Transcript')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
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
                        $record->loadMissing('student.academicRecords');

                        if ($record->student->academicRecords->isEmpty()) {
                            Notification::make()
                                ->title('Transcript cannot be issued')
                                ->body('The student has no academic records, so this official transcript cannot be issued yet.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $transcriptNumber = filled($data['transcript_number'] ?? null)
                            ? trim((string) $data['transcript_number'])
                            : self::generateTranscriptNumber($record);

                        $duplicateExists = OfficialTranscript::query()
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

                            return;
                        }

                        DB::transaction(function () use ($record, $data, $transcriptNumber): void {
                            $record->forceFill([
                                'transcript_number' => $transcriptNumber,
                                'status' => 'issued',
                                'purpose' => $data['purpose'] ?: null,
                                'issued_at' => $data['issued_at'],
                                'recipient_name' => $data['recipient_name'] ?: null,
                                'recipient_email' => $data['recipient_email'] ?: null,
                                'delivery_method' => $data['delivery_method'],
                                'registrar_notes' => $data['registrar_notes'] ?: null,
                            ]);

                            if (! $record->requested_at) {
                                $record->requested_at = null;
                            }

                            $record->save();
                        });

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
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
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
            ->where('institution_id', $record->institution_id)
            ->where('transcript_number', $candidate)
            ->exists());

        return $candidate;
    }
}

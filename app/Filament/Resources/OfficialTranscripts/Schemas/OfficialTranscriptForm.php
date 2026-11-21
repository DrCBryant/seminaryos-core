<?php

namespace App\Filament\Resources\OfficialTranscripts\Schemas;

use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\Student;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class OfficialTranscriptForm
{
    public const DELIVERY_METHOD_OPTIONS = [
        'internal' => 'Internal',
        'email' => 'Email',
        'printed' => 'Printed',
        'pickup' => 'Pickup',
        'postal_mail' => 'Postal Mail',
        'other' => 'Other',
    ];

    /** @param array<string, mixed> $data */
    public static function validateForSave(array $data): void
    {
        if (($data['status'] ?? null) === 'issued') {
            throw ValidationException::withMessages(['data.status' => 'Use Issue Transcript to finalize an official transcript.']);
        }

        $institutionId = $data['institution_id'];
        $currentInstitutionId = auth()->user()?->current_institution_id;

        if ($currentInstitutionId !== null && (int) $institutionId !== (int) $currentInstitutionId) {
            throw ValidationException::withMessages(['data.institution_id' => 'Select your current institution.']);
        }

        if (! Student::query()->where('institution_id', $institutionId)->whereKey($data['student_id'])->exists()) {
            throw ValidationException::withMessages(['data.student_id' => 'Select a student from the transcript institution.']);
        }
    }

    public static function configure(Schema $schema): Schema
    {
        $issued = fn (?OfficialTranscript $record): bool => $record?->status === 'issued';

        return $schema
            ->components([
                Section::make('Official Transcript Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('institution_id')
                                    ->label('Institution')
                                    ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled($issued),
                                Select::make('student_id')
                                    ->label('Student')
                                    ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => "{$record->full_name} ({$record->student_number})")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled($issued),
                                TextInput::make('transcript_number')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled($issued),
                                Select::make('status')
                                    ->options(fn (?OfficialTranscript $record): array => $issued($record)
                                        ? OfficialTranscript::statusOptions()
                                        : Arr::except(OfficialTranscript::statusOptions(), ['issued']))
                                    ->default('draft')
                                    ->required()
                                    ->disabled($issued),
                                TextInput::make('purpose')
                                    ->maxLength(255)
                                    ->disabled($issued),
                                Select::make('delivery_method')
                                    ->options(self::DELIVERY_METHOD_OPTIONS)
                                    ->disabled($issued),
                                DateTimePicker::make('requested_at')
                                    ->label('Requested date')
                                    ->seconds(false)
                                    ->disabled($issued),
                                DateTimePicker::make('issued_at')
                                    ->label('Issued date')
                                    ->seconds(false)
                                    ->disabled($issued),
                                TextInput::make('recipient_name')
                                    ->maxLength(255)
                                    ->disabled($issued),
                                TextInput::make('recipient_email')
                                    ->email()
                                    ->maxLength(255)
                                    ->disabled($issued),
                            ]),
                        Textarea::make('registrar_notes')
                            ->rows(4),
                        Textarea::make('internal_notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

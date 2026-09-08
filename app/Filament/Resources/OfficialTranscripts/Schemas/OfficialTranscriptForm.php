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
                                    ->options(OfficialTranscript::statusOptions())
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

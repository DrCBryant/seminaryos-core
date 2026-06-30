<?php

namespace App\Filament\Resources\OfficialTranscripts\Schemas;

use App\Models\Institution;
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
    public const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'requested' => 'Requested',
        'under_review' => 'Under Review',
        'issued' => 'Issued',
        'voided' => 'Voided',
        'archived' => 'Archived',
    ];

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
                                    ->required(),
                                Select::make('student_id')
                                    ->label('Student')
                                    ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => "{$record->full_name} ({$record->student_number})")
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('transcript_number')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options(self::STATUS_OPTIONS)
                                    ->default('draft')
                                    ->required(),
                                TextInput::make('purpose')
                                    ->maxLength(255),
                                Select::make('delivery_method')
                                    ->options(self::DELIVERY_METHOD_OPTIONS),
                                DateTimePicker::make('requested_at')
                                    ->label('Requested date')
                                    ->seconds(false),
                                DateTimePicker::make('issued_at')
                                    ->label('Issued date')
                                    ->seconds(false),
                                TextInput::make('recipient_name')
                                    ->maxLength(255),
                                TextInput::make('recipient_email')
                                    ->email()
                                    ->maxLength(255),
                            ]),
                        Textarea::make('registrar_notes')
                            ->rows(4),
                        Textarea::make('internal_notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

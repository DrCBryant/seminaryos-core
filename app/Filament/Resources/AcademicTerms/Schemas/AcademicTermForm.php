<?php

namespace App\Filament\Resources\AcademicTerms\Schemas;

use App\Models\AcademicTerm;
use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AcademicTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Academic Term Details')
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
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('academic_year')
                                    ->label('Academic year')
                                    ->required()
                                    ->maxLength(20)
                                    ->placeholder('2026-2027'),
                                Select::make('term_type')
                                    ->label('Term type')
                                    ->options(AcademicTerm::termTypeOptions())
                                    ->required(),
                                Select::make('status')
                                    ->options(AcademicTerm::statusOptions())
                                    ->required(),
                                DatePicker::make('start_date')
                                    ->label('Start date')
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('End date')
                                    ->required()
                                    ->afterOrEqual('start_date'),
                                DatePicker::make('registration_start_date')
                                    ->label('Registration start date')
                                    ->helperText('Optional ordinary registration boundary. Blank dates do not mean registration is closed.'),
                                DatePicker::make('registration_end_date')
                                    ->label('Registration end date')
                                    ->afterOrEqual(fn (Get $get): ?string => filled($get('registration_start_date')) ? 'registration_start_date' : null)
                                    ->helperText('Administrative enrollment remains allowed outside this window. Registration dates do not change term status.'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

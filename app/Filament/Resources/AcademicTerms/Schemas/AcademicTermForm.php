<?php

namespace App\Filament\Resources\AcademicTerms\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                                    ->options([
                                        'fall' => 'Fall',
                                        'spring' => 'Spring',
                                        'summer' => 'Summer',
                                        'winter' => 'Winter',
                                        'intensive' => 'Intensive',
                                        'custom' => 'Custom',
                                    ])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'open' => 'Open',
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'archived' => 'Archived',
                                    ])
                                    ->required(),
                                DatePicker::make('start_date')
                                    ->label('Start date')
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('End date')
                                    ->required()
                                    ->afterOrEqual('start_date'),
                                DatePicker::make('registration_start_date')
                                    ->label('Registration start date'),
                                DatePicker::make('registration_end_date')
                                    ->label('Registration end date')
                                    ->afterOrEqual('registration_start_date'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

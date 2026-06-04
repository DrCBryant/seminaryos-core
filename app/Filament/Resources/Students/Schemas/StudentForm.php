<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Program;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(50),
                                TextInput::make('student_number')
                                    ->required()
                                    ->maxLength(100),
                                Select::make('program_id')
                                    ->label('Program')
                                    ->relationship('program', 'title', fn ($query) => $query->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Program $record): string => "{$record->code} - {$record->title}")
                                    ->searchable(['code', 'title'])
                                    ->preload(),
                                Select::make('status')
                                    ->options([
                                        'prospective' => 'Prospective',
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'graduated' => 'Graduated',
                                        'withdrawn' => 'Withdrawn',
                                    ])
                                    ->required(),
                                DatePicker::make('enrollment_date'),
                            ]),
                        Textarea::make('notes')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

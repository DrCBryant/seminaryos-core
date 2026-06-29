<?php

namespace App\Filament\Resources\Faculty\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FacultyForm
{
    public const STATUS_OPTIONS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'adjunct' => 'Adjunct',
        'emeritus' => 'Emeritus',
        'former' => 'Former',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Faculty Details')
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
                                TextInput::make('first_name')
                                    ->label('First name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->label('Last name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(50),
                                TextInput::make('title')
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options(self::STATUS_OPTIONS)
                                    ->required()
                                    ->default('active'),
                                Toggle::make('is_public')
                                    ->label('Public visibility')
                                    ->default(false),
                                DatePicker::make('started_at')
                                    ->label('Started at'),
                                DatePicker::make('ended_at')
                                    ->label('Ended at')
                                    ->afterOrEqual('started_at'),
                            ]),
                        Textarea::make('bio')
                            ->rows(5),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

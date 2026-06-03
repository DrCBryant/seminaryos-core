<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Program Details')
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
                                TextInput::make('code')
                                    ->label('Program code')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('title')
                                    ->label('Program name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('credential_type')
                                    ->label('Degree level')
                                    ->maxLength(100),
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'archived' => 'Archived',
                                    ])
                                    ->required(),
                                Toggle::make('is_public')
                                    ->label('Public visibility'),
                                TextInput::make('seo_title')
                                    ->maxLength(255),
                            ]),
                        Textarea::make('description')
                            ->rows(6),
                        Textarea::make('seo_description')
                            ->rows(3),
                    ]),
            ]);
    }
}

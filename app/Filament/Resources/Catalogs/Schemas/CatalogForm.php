<?php

namespace App\Filament\Resources\Catalogs\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CatalogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Catalog Details')
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
                                TextInput::make('title')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('academic_year')
                                    ->required()
                                    ->maxLength(50),
                                Select::make('status')
                                    ->label('Generated status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'generated' => 'Generated',
                                        'published' => 'Published',
                                        'archived' => 'Archived',
                                    ])
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Active / inactive'),
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(255),
                                Placeholder::make('generated_status_note')
                                    ->label('Note')
                                    ->content('The current schema stores generation state in the status column.'),
                            ]),
                        Textarea::make('description')
                            ->rows(6),
                    ]),
            ]);
    }
}

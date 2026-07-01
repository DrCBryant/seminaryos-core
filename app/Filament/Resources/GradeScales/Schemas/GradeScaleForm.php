<?php

namespace App\Filament\Resources\GradeScales\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GradeScaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Grade Scale Details')
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
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Textarea::make('notes')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

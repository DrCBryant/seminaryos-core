<?php

namespace App\Filament\Resources\Institutions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institution')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(255),
                                Select::make('type')
                                    ->options([
                                        'seminary' => 'Seminary',
                                        'university' => 'University',
                                        'college' => 'College',
                                        'institute' => 'Institute',
                                    ])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'suspended' => 'Suspended',
                                    ])
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}

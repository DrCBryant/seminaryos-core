<?php

namespace App\Filament\Resources\Programs\RelationManagers;

use App\Models\ProgramRequirementGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProgramRequirementGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'programRequirementGroups';

    protected static ?string $title = 'Program Requirement Groups';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Hidden::make('institution_id')
                                ->default(fn () => $this->getOwnerRecord()->institution_id),
                            Select::make('group_type')
                                ->label('Group type')
                                ->options(ProgramRequirementGroup::TYPES)
                                ->required(),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('sort_order')
                                ->numeric(),
                            TextInput::make('required_credits')
                                ->numeric(),
                            TextInput::make('minimum_gpa')
                                ->numeric(),
                            Toggle::make('is_active')
                                ->default(true),
                        ]),
                    Textarea::make('description')
                        ->rows(3),
                    Textarea::make('notes')
                        ->rows(3),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('required_credits')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('minimum_gpa')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

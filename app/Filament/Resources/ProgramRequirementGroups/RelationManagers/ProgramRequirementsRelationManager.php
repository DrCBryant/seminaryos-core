<?php

namespace App\Filament\Resources\ProgramRequirementGroups\RelationManagers;

use App\Models\ProgramRequirement;
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
use Illuminate\Database\Eloquent\Builder;

class ProgramRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'programRequirements';

    protected static ?string $title = 'Program Requirements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Hidden::make('institution_id')
                                ->default(fn () => $this->getOwnerRecord()->institution_id),
                            Hidden::make('program_id')
                                ->default(fn () => $this->getOwnerRecord()->program_id),
                            Select::make('course_id')
                                ->relationship('course', 'title', fn (Builder $query) => $query->orderBy('title'))
                                ->searchable()
                                ->preload(),
                            Select::make('requirement_type')
                                ->label('Requirement type')
                                ->options(ProgramRequirement::TYPES)
                                ->required(),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('required_credits')
                                ->numeric(),
                            TextInput::make('minimum_grade')
                                ->maxLength(50),
                            TextInput::make('minimum_grade_points')
                                ->numeric(),
                            TextInput::make('sort_order')
                                ->numeric(),
                            Toggle::make('allow_substitution')
                                ->default(false),
                            Toggle::make('is_required')
                                ->default(true),
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
                TextColumn::make('requirement_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('course.code')
                    ->label('Course')
                    ->searchable(),
                TextColumn::make('required_credits')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('minimum_grade'),
                TextColumn::make('minimum_grade_points')
                    ->numeric(decimalPlaces: 2),
                IconColumn::make('is_required')
                    ->boolean(),
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

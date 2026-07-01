<?php

namespace App\Filament\Resources\ProgramRequirementGroups;

use App\Filament\Resources\ProgramRequirementGroups\Pages\CreateProgramRequirementGroup;
use App\Filament\Resources\ProgramRequirementGroups\Pages\EditProgramRequirementGroup;
use App\Filament\Resources\ProgramRequirementGroups\Pages\ListProgramRequirementGroups;
use App\Filament\Resources\ProgramRequirementGroups\RelationManagers\ProgramRequirementsRelationManager;
use App\Models\ProgramRequirementGroup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProgramRequirementGroupResource extends Resource
{
    protected static ?string $model = ProgramRequirementGroup::class;

    protected static ?string $slug = 'program-requirement-groups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $modelLabel = 'Program Requirement Group';

    protected static ?string $pluralModelLabel = 'Program Requirement Groups';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program Requirement Group')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('institution_id')
                                ->relationship('institution', 'name', fn (Builder $query) => $query->orderBy('name'))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('program_id')
                                ->relationship('program', 'title', fn (Builder $query) => $query->orderBy('title'))
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('group_type')
                                ->label('Group type')
                                ->options(ProgramRequirementGroup::TYPES)
                                ->required(),
                            TextInput::make('required_credits')
                                ->numeric(),
                            TextInput::make('minimum_gpa')
                                ->numeric(),
                            TextInput::make('sort_order')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program.title')
                    ->label('Program')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group_type')
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
            ->filters([
                SelectFilter::make('group_type')
                    ->options(ProgramRequirementGroup::TYPES),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProgramRequirementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramRequirementGroups::route('/'),
            'create' => CreateProgramRequirementGroup::route('/create'),
            'edit' => EditProgramRequirementGroup::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

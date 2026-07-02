<?php

namespace App\Filament\Resources\ProgramRequirementSubstitutions;

use App\Filament\Resources\ProgramRequirementSubstitutions\Pages\CreateProgramRequirementSubstitution;
use App\Filament\Resources\ProgramRequirementSubstitutions\Pages\EditProgramRequirementSubstitution;
use App\Filament\Resources\ProgramRequirementSubstitutions\Pages\ListProgramRequirementSubstitutions;
use App\Filament\Resources\ProgramRequirementSubstitutions\Schemas\ProgramRequirementSubstitutionForm;
use App\Filament\Resources\ProgramRequirementSubstitutions\Tables\ProgramRequirementSubstitutionsTable;
use App\Models\ProgramRequirementSubstitution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProgramRequirementSubstitutionResource extends Resource
{
    protected static ?string $model = ProgramRequirementSubstitution::class;

    protected static ?string $slug = 'requirement-substitutions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Requirement Substitutions';

    protected static ?string $modelLabel = 'Requirement Substitution';

    protected static ?string $pluralModelLabel = 'Requirement Substitutions';

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return ProgramRequirementSubstitutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramRequirementSubstitutionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramRequirementSubstitutions::route('/'),
            'create' => CreateProgramRequirementSubstitution::route('/create'),
            'edit' => EditProgramRequirementSubstitution::route('/{record}/edit'),
        ];
    }
}

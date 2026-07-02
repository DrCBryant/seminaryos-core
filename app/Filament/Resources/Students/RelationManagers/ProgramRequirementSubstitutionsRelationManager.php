<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\ProgramRequirementSubstitutions\Schemas\ProgramRequirementSubstitutionForm;
use App\Filament\Resources\ProgramRequirementSubstitutions\Tables\ProgramRequirementSubstitutionsTable;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProgramRequirementSubstitutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'programRequirementSubstitutions';

    protected static ?string $title = 'Requirement Substitutions';

    public function form(Schema $schema): Schema
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();

        return ProgramRequirementSubstitutionForm::configure($schema, $student);
    }

    public function table(Table $table): Table
    {
        return ProgramRequirementSubstitutionsTable::configure($table)
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

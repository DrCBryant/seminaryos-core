<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\StudentRequirementEvidence\Schemas\StudentRequirementEvidenceForm;
use App\Filament\Resources\StudentRequirementEvidence\Tables\StudentRequirementEvidenceTable;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StudentRequirementEvidenceRelationManager extends RelationManager
{
    protected static string $relationship = 'studentRequirementEvidence';

    protected static ?string $title = 'Requirement Evidence';

    public function form(Schema $schema): Schema
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();

        return StudentRequirementEvidenceForm::configure($schema, $student);
    }

    public function table(Table $table): Table
    {
        return StudentRequirementEvidenceTable::configure($table)
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

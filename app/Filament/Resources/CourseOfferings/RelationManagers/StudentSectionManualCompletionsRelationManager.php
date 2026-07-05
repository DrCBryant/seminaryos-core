<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Filament\Resources\StudentSectionManualCompletions\Schemas\StudentSectionManualCompletionForm;
use App\Filament\Resources\StudentSectionManualCompletions\Tables\StudentSectionManualCompletionsTable;
use App\Models\CourseOffering;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StudentSectionManualCompletionsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentSectionManualCompletions';

    protected static ?string $title = 'Manual Completions';

    public function form(Schema $schema): Schema
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->getOwnerRecord();

        return StudentSectionManualCompletionForm::configure($schema, $courseOffering);
    }

    public function table(Table $table): Table
    {
        return StudentSectionManualCompletionsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Filament\Resources\StudentSectionSubmissions\Schemas\StudentSectionSubmissionForm;
use App\Filament\Resources\StudentSectionSubmissions\Tables\StudentSectionSubmissionsTable;
use App\Models\CourseOffering;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StudentSectionSubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentSectionSubmissions';

    protected static ?string $title = 'Student Submissions';

    public function form(Schema $schema): Schema
    {
        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->getOwnerRecord();

        return StudentSectionSubmissionForm::configure($schema, $courseOffering);
    }

    public function table(Table $table): Table
    {
        return StudentSectionSubmissionsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

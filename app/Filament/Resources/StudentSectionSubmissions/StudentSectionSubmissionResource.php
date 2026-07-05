<?php

namespace App\Filament\Resources\StudentSectionSubmissions;

use App\Filament\Resources\StudentSectionSubmissions\Pages\CreateStudentSectionSubmission;
use App\Filament\Resources\StudentSectionSubmissions\Pages\EditStudentSectionSubmission;
use App\Filament\Resources\StudentSectionSubmissions\Pages\ListStudentSectionSubmissions;
use App\Filament\Resources\StudentSectionSubmissions\Schemas\StudentSectionSubmissionForm;
use App\Filament\Resources\StudentSectionSubmissions\Tables\StudentSectionSubmissionsTable;
use App\Models\StudentSectionSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentSectionSubmissionResource extends Resource
{
    protected static ?string $model = StudentSectionSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Student Submissions';

    protected static ?string $modelLabel = 'Student Submission';

    protected static ?string $pluralModelLabel = 'Student Submissions';

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return StudentSectionSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentSectionSubmissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentSectionSubmissions::route('/'),
            'create' => CreateStudentSectionSubmission::route('/create'),
            'edit' => EditStudentSectionSubmission::route('/{record}/edit'),
        ];
    }
}

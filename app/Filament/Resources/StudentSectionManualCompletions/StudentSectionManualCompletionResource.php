<?php

namespace App\Filament\Resources\StudentSectionManualCompletions;

use App\Filament\Resources\StudentSectionManualCompletions\Pages\CreateStudentSectionManualCompletion;
use App\Filament\Resources\StudentSectionManualCompletions\Pages\EditStudentSectionManualCompletion;
use App\Filament\Resources\StudentSectionManualCompletions\Pages\ListStudentSectionManualCompletions;
use App\Filament\Resources\StudentSectionManualCompletions\Schemas\StudentSectionManualCompletionForm;
use App\Filament\Resources\StudentSectionManualCompletions\Tables\StudentSectionManualCompletionsTable;
use App\Models\StudentSectionManualCompletion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentSectionManualCompletionResource extends Resource
{
    protected static ?string $model = StudentSectionManualCompletion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Manual Completions';

    protected static ?string $modelLabel = 'Manual Completion';

    protected static ?string $pluralModelLabel = 'Manual Completions';

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return StudentSectionManualCompletionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentSectionManualCompletionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentSectionManualCompletions::route('/'),
            'create' => CreateStudentSectionManualCompletion::route('/create'),
            'edit' => EditStudentSectionManualCompletion::route('/{record}/edit'),
        ];
    }
}

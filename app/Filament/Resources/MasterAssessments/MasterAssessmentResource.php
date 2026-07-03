<?php

namespace App\Filament\Resources\MasterAssessments;

use App\Filament\Resources\MasterAssessments\Pages\CreateMasterAssessment;
use App\Filament\Resources\MasterAssessments\Pages\EditMasterAssessment;
use App\Filament\Resources\MasterAssessments\Pages\ListMasterAssessments;
use App\Filament\Resources\MasterAssessments\RelationManagers\StudentMasterAssessmentAttemptsRelationManager;
use App\Filament\Resources\MasterAssessments\Schemas\MasterAssessmentForm;
use App\Filament\Resources\MasterAssessments\Tables\MasterAssessmentsTable;
use App\Models\MasterAssessment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasterAssessmentResource extends Resource
{
    protected static ?string $model = MasterAssessment::class;

    protected static ?string $slug = 'master-assessments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Master Assessments';

    protected static ?string $modelLabel = 'Master Assessment';

    protected static ?string $pluralModelLabel = 'Master Assessments';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return MasterAssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterAssessmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StudentMasterAssessmentAttemptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMasterAssessments::route('/'),
            'create' => CreateMasterAssessment::route('/create'),
            'edit' => EditMasterAssessment::route('/{record}/edit'),
        ];
    }
}

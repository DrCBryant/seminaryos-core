<?php

namespace App\Filament\Resources\StudentRequirementEvidence;

use App\Filament\Resources\StudentRequirementEvidence\Pages\CreateStudentRequirementEvidence;
use App\Filament\Resources\StudentRequirementEvidence\Pages\EditStudentRequirementEvidence;
use App\Filament\Resources\StudentRequirementEvidence\Pages\ListStudentRequirementEvidence;
use App\Filament\Resources\StudentRequirementEvidence\Schemas\StudentRequirementEvidenceForm;
use App\Filament\Resources\StudentRequirementEvidence\Tables\StudentRequirementEvidenceTable;
use App\Models\StudentRequirementEvidence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentRequirementEvidenceResource extends Resource
{
    protected static ?string $model = StudentRequirementEvidence::class;

    protected static ?string $slug = 'requirement-evidence';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Requirement Evidence';

    protected static ?string $modelLabel = 'Requirement Evidence';

    protected static ?string $pluralModelLabel = 'Requirement Evidence';

    protected static ?string $recordTitleAttribute = 'evidence_title';

    public static function form(Schema $schema): Schema
    {
        return StudentRequirementEvidenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentRequirementEvidenceTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentRequirementEvidence::route('/'),
            'create' => CreateStudentRequirementEvidence::route('/create'),
            'edit' => EditStudentRequirementEvidence::route('/{record}/edit'),
        ];
    }
}

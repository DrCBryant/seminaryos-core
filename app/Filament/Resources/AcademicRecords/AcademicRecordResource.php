<?php

namespace App\Filament\Resources\AcademicRecords;

use App\Filament\Resources\AcademicRecords\Pages\CreateAcademicRecord;
use App\Filament\Resources\AcademicRecords\Pages\EditAcademicRecord;
use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Filament\Resources\AcademicRecords\Schemas\AcademicRecordForm;
use App\Filament\Resources\AcademicRecords\Tables\AcademicRecordsTable;
use App\Models\AcademicRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AcademicRecordResource extends Resource
{
    protected static ?string $model = AcademicRecord::class;

    protected static ?string $slug = 'academic-records';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Academic Records';

    protected static ?string $recordTitleAttribute = 'course_title';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $modelLabel = 'Academic Record';

    protected static ?string $pluralModelLabel = 'Academic Records';

    public static function form(Schema $schema): Schema
    {
        return AcademicRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicRecords::route('/'),
            'create' => CreateAcademicRecord::route('/create'),
            'edit' => EditAcademicRecord::route('/{record}/edit'),
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

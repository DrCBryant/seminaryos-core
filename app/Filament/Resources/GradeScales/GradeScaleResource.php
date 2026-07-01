<?php

namespace App\Filament\Resources\GradeScales;

use App\Filament\Resources\GradeScales\Pages\CreateGradeScale;
use App\Filament\Resources\GradeScales\Pages\EditGradeScale;
use App\Filament\Resources\GradeScales\Pages\ListGradeScales;
use App\Filament\Resources\GradeScales\RelationManagers\GradeValuesRelationManager;
use App\Filament\Resources\GradeScales\Schemas\GradeScaleForm;
use App\Filament\Resources\GradeScales\Tables\GradeScalesTable;
use App\Models\GradeScale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GradeScaleResource extends Resource
{
    protected static ?string $model = GradeScale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Grade Scales';

    protected static ?string $modelLabel = 'Grade Scale';

    protected static ?string $pluralModelLabel = 'Grade Scales';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GradeScaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradeScalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GradeValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGradeScales::route('/'),
            'create' => CreateGradeScale::route('/create'),
            'edit' => EditGradeScale::route('/{record}/edit'),
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

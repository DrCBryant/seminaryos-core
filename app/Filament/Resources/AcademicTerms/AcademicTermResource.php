<?php

namespace App\Filament\Resources\AcademicTerms;

use App\Filament\Resources\AcademicTerms\Pages\CreateAcademicTerm;
use App\Filament\Resources\AcademicTerms\Pages\EditAcademicTerm;
use App\Filament\Resources\AcademicTerms\Pages\ListAcademicTerms;
use App\Filament\Resources\AcademicTerms\Schemas\AcademicTermForm;
use App\Filament\Resources\AcademicTerms\Tables\AcademicTermsTable;
use App\Models\AcademicTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AcademicTermResource extends Resource
{
    protected static ?string $model = AcademicTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    public static function form(Schema $schema): Schema
    {
        return AcademicTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicTermsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicTerms::route('/'),
            'create' => CreateAcademicTerm::route('/create'),
            'edit' => EditAcademicTerm::route('/{record}/edit'),
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

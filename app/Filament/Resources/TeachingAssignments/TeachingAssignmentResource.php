<?php

namespace App\Filament\Resources\TeachingAssignments;

use App\Filament\Resources\TeachingAssignments\Pages\CreateTeachingAssignment;
use App\Filament\Resources\TeachingAssignments\Pages\EditTeachingAssignment;
use App\Filament\Resources\TeachingAssignments\Pages\ListTeachingAssignments;
use App\Filament\Resources\TeachingAssignments\Schemas\TeachingAssignmentForm;
use App\Filament\Resources\TeachingAssignments\Tables\TeachingAssignmentsTable;
use App\Models\TeachingAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeachingAssignmentResource extends Resource
{
    protected static ?string $model = TeachingAssignment::class;

    protected static ?string $slug = 'teaching-assignments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Teaching Assignments';

    protected static ?string $recordTitleAttribute = 'uuid';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $modelLabel = 'Teaching Assignment';

    protected static ?string $pluralModelLabel = 'Teaching Assignments';

    public static function form(Schema $schema): Schema
    {
        return TeachingAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachingAssignmentsTable::configure($table);
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
            'index' => ListTeachingAssignments::route('/'),
            'create' => CreateTeachingAssignment::route('/create'),
            'edit' => EditTeachingAssignment::route('/{record}/edit'),
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

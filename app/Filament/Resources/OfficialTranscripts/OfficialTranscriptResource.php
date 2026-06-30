<?php

namespace App\Filament\Resources\OfficialTranscripts;

use App\Filament\Resources\OfficialTranscripts\Pages\CreateOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\EditOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\ListOfficialTranscripts;
use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use App\Filament\Resources\OfficialTranscripts\Tables\OfficialTranscriptsTable;
use App\Models\OfficialTranscript;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfficialTranscriptResource extends Resource
{
    protected static ?string $model = OfficialTranscript::class;

    protected static ?string $slug = 'official-transcripts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Official Transcripts';

    protected static ?string $recordTitleAttribute = 'transcript_number';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $modelLabel = 'Official Transcript';

    protected static ?string $pluralModelLabel = 'Official Transcripts';

    public static function form(Schema $schema): Schema
    {
        return OfficialTranscriptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficialTranscriptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfficialTranscripts::route('/'),
            'create' => CreateOfficialTranscript::route('/create'),
            'edit' => EditOfficialTranscript::route('/{record}/edit'),
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

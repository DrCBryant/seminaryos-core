<?php

namespace App\Filament\Resources\TranscriptSettings;

use App\Filament\Resources\TranscriptSettings\Pages\CreateTranscriptSetting;
use App\Filament\Resources\TranscriptSettings\Pages\EditTranscriptSetting;
use App\Filament\Resources\TranscriptSettings\Pages\ListTranscriptSettings;
use App\Filament\Resources\TranscriptSettings\Schemas\TranscriptSettingForm;
use App\Filament\Resources\TranscriptSettings\Tables\TranscriptSettingsTable;
use App\Models\TranscriptSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TranscriptSettingResource extends Resource
{
    protected static ?string $model = TranscriptSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Transcript Settings';

    protected static ?string $modelLabel = 'Transcript Setting';

    protected static ?string $pluralModelLabel = 'Transcript Settings';

    protected static ?string $recordTitleAttribute = 'transcript_title';

    public static function form(Schema $schema): Schema
    {
        return TranscriptSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TranscriptSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranscriptSettings::route('/'),
            'create' => CreateTranscriptSetting::route('/create'),
            'edit' => EditTranscriptSetting::route('/{record}/edit'),
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

<?php

namespace App\Filament\Resources\Websites\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebsiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Website')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('institution_id')
                                    ->label('Institution')
                                    ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('domain')
                                    ->maxLength(255)
                                    ->helperText('Optional. Existing schema does not include a slug field.'),
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'archived' => 'Archived',
                                    ])
                                    ->required(),
                                Toggle::make('is_public')
                                    ->label('Public visibility')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Not stored because the existing Website model/schema has no public visibility field.'),
                                Placeholder::make('slug_notice')
                                    ->label('Slug')
                                    ->content('Not available in the current Website model/schema.'),
                                Placeholder::make('seo_title_notice')
                                    ->label('SEO title')
                                    ->content('Not available in the current Website model/schema.'),
                                Placeholder::make('seo_description_notice')
                                    ->label('SEO description')
                                    ->content('Not available in the current Website model/schema.'),
                            ]),
                        Textarea::make('settings')
                            ->label('Settings JSON')
                            ->rows(6)
                            ->formatStateUsing(fn ($state) => blank($state) ? null : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->dehydrateStateUsing(fn (?string $state) => blank($state) ? null : json_decode($state, true))
                            ->helperText('Optional raw settings payload.'),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\TranscriptSettings\Schemas;

use App\Models\Institution;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TranscriptSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transcript Display')
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
                                TextInput::make('transcript_title')
                                    ->label('Transcript title')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('Official Transcript'),
                                TextInput::make('registrar_name')
                                    ->label('Registrar name')
                                    ->maxLength(255),
                                TextInput::make('registrar_title')
                                    ->label('Registrar title')
                                    ->maxLength(255),
                            ]),
                        Textarea::make('certification_statement')
                            ->label('Certification statement')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('footer_statement')
                            ->label('Footer statement')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('grading_scale_note')
                            ->label('Grading scale note')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('accreditation_note')
                            ->label('Accreditation note')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('transcript_disclaimer')
                            ->label('Transcript disclaimer')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Display Toggles')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('show_recipient_info')
                                    ->label('Show recipient information')
                                    ->default(true),
                                Toggle::make('show_delivery_method')
                                    ->label('Show delivery method')
                                    ->default(true),
                                Toggle::make('show_purpose')
                                    ->label('Show purpose')
                                    ->default(true),
                                Toggle::make('show_grade_points')
                                    ->label('Show grade points')
                                    ->default(false),
                                Toggle::make('show_status')
                                    ->label('Show status')
                                    ->default(true),
                                Toggle::make('is_active')
                                    ->label('Active setting')
                                    ->default(true),
                            ]),
                    ]),
                Section::make('Internal Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

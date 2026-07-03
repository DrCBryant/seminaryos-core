<?php

namespace App\Filament\Resources\MasterAssessments\Schemas;

use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\MasterAssessment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MasterAssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Master Assessment Details')
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
                                Select::make('course_offering_id')
                                    ->label('Course offering')
                                    ->relationship('courseOffering', 'section_code', fn ($query) => $query->orderByDesc('academic_term_id')->orderBy('section_code'))
                                    ->getOptionLabelFromRecordUsing(fn (CourseOffering $record): string => trim("{$record->course?->code} — {$record->academicTerm?->name} ({$record->academicTerm?->academic_year}) — {$record->section_code}"))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('passing_threshold')
                                    ->label('Passing threshold')
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options(MasterAssessment::STATUS_OPTIONS)
                                    ->default(MasterAssessment::STATUS_DRAFT)
                                    ->required(),
                            ]),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('competency_outcomes')
                            ->label('Competency outcomes')
                            ->rows(6)
                            ->columnSpanFull(),
                        Textarea::make('rubric')
                            ->rows(6)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

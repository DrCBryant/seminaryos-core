<?php

namespace App\Filament\Resources\CourseOfferings\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseOfferingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Offering Details')
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
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'title', fn ($query) => $query->orderBy('code')->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('academic_term_id')
                                    ->label('Academic term')
                                    ->relationship('academicTerm', 'name', fn ($query) => $query->orderByDesc('academic_year')->orderBy('start_date'))
                                    ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => "{$record->name} ({$record->academic_year})")
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('section_code')
                                    ->label('Section code')
                                    ->maxLength(255),
                                TextInput::make('title')
                                    ->maxLength(255),
                                Select::make('delivery_mode')
                                    ->options(CourseOffering::DELIVERY_MODE_OPTIONS)
                                    ->required(),
                                TextInput::make('location')
                                    ->maxLength(255),
                                TextInput::make('meeting_pattern')
                                    ->maxLength(255),
                                DatePicker::make('start_date')
                                    ->label('Start date'),
                                DatePicker::make('end_date')
                                    ->label('End date'),
                                TextInput::make('capacity')
                                    ->numeric(),
                                Select::make('status')
                                    ->options(CourseOffering::STATUS_OPTIONS)
                                    ->default('planned')
                                    ->required(),
                            ]),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

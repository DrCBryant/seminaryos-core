<?php

namespace App\Filament\Resources\AttendanceSessions\Schemas;

use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance Session Details')
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
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $offering = CourseOffering::query()->find($state);

                                        if (! $offering) {
                                            return;
                                        }

                                        $set('institution_id', $offering->institution_id);
                                        $set('course_id', $offering->course_id);
                                        $set('academic_term_id', $offering->academic_term_id);
                                    }),
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'title', fn ($query) => $query->orderBy('title'))
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
                                DatePicker::make('session_date')
                                    ->required(),
                                Select::make('status')
                                    ->options(AttendanceSession::STATUS_OPTIONS)
                                    ->default('planned')
                                    ->required(),
                                TimePicker::make('start_time')
                                    ->seconds(false),
                                TimePicker::make('end_time')
                                    ->seconds(false),
                                TextInput::make('topic')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

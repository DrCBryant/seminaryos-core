<?php

namespace App\Filament\Resources\CourseEnrollments\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\Student;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Enrollment Details')
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
                                Select::make('student_id')
                                    ->label('Student')
                                    ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('course_offering_id')
                                    ->label('Course offering')
                                    ->relationship('courseOffering', 'section_code', fn ($query) => $query->orderByDesc('academic_term_id')->orderBy('section_code'))
                                    ->getOptionLabelFromRecordUsing(fn (CourseOffering $record): string => trim("{$record->course?->code} — {$record->academicTerm?->name} — {$record->section_code}"))
                                    ->searchable()
                                    ->preload()
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
                                    ->required()
                                    ->helperText('Used for legacy/manual enrollments when no course offering is selected.'),
                                Select::make('academic_term_id')
                                    ->label('Academic term')
                                    ->relationship('academicTerm', 'name', fn ($query) => $query->orderByDesc('academic_year')->orderBy('start_date'))
                                    ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => "{$record->name} ({$record->academic_year})")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Used for legacy/manual enrollments when no course offering is selected.'),
                                Select::make('status')
                                    ->options([
                                        'enrolled' => 'Enrolled',
                                        'dropped' => 'Dropped',
                                        'withdrawn' => 'Withdrawn',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'incomplete' => 'Incomplete',
                                    ])
                                    ->required(),
                                TextInput::make('final_grade')
                                    ->label('Final grade')
                                    ->maxLength(20),
                                DateTimePicker::make('enrolled_at')
                                    ->label('Enrolled date'),
                                DateTimePicker::make('completed_at')
                                    ->label('Completed date')
                                    ->afterOrEqual('enrolled_at'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

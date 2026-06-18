<?php

namespace App\Filament\Resources\CourseEnrollments\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Student;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

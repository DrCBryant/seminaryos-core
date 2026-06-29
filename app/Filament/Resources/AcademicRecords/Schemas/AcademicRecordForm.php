<?php

namespace App\Filament\Resources\AcademicRecords\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Institution;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AcademicRecordForm
{
    public const STATUS_OPTIONS = [
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'withdrawn' => 'Withdrawn',
        'transfer' => 'Transfer',
        'waived' => 'Waived',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Academic Record Details')
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
                                    ->preload(),
                                Select::make('course_enrollment_id')
                                    ->label('Course enrollment')
                                    ->relationship('courseEnrollment', 'uuid', fn ($query) => $query->latest('id'))
                                    ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): string => "{$record->student->full_name} — {$record->course->title}")
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('course_code')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('course_title')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('credits_attempted')
                                    ->numeric()
                                    ->inputMode('decimal'),
                                TextInput::make('credits_earned')
                                    ->numeric()
                                    ->inputMode('decimal'),
                                TextInput::make('final_grade')
                                    ->label('Final grade')
                                    ->maxLength(20),
                                TextInput::make('grade_points')
                                    ->numeric()
                                    ->inputMode('decimal'),
                                Select::make('status')
                                    ->options(self::STATUS_OPTIONS)
                                    ->default('in_progress')
                                    ->required(),
                                DatePicker::make('completed_at')
                                    ->label('Completed date'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\StudentSectionManualCompletions\Schemas;

use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\Student;
use App\Models\StudentSectionManualCompletion;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentSectionManualCompletionForm
{
    public static function configure(Schema $schema, ?CourseOffering $ownerCourseOffering = null): Schema
    {
        return $schema
            ->components([
                Section::make('Manual Completion')
                    ->schema([
                        Grid::make(2)
                            ->schema(array_values(array_filter([
                                $ownerCourseOffering === null
                                    ? Select::make('institution_id')
                                        ->label('Institution')
                                        ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                        ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                    : Hidden::make('institution_id')
                                        ->default($ownerCourseOffering->institution_id)
                                        ->required(),
                                $ownerCourseOffering === null
                                    ? Select::make('course_offering_id')
                                        ->label('Course offering')
                                        ->relationship('courseOffering', 'section_code', fn ($query) => $query->orderByDesc('academic_term_id')->orderBy('section_code'))
                                        ->getOptionLabelFromRecordUsing(fn (CourseOffering $record): string => trim("{$record->course?->code} — {$record->academicTerm?->name} — {$record->section_code}"))
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                    : Hidden::make('course_offering_id')
                                        ->default($ownerCourseOffering->id)
                                        ->required(),
                                Select::make('course_enrollment_id')
                                    ->label('Course enrollment')
                                    ->relationship('courseEnrollment', 'uuid', fn ($query) => $query->orderByDesc('enrolled_at')->orderByDesc('id'))
                                    ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): string => trim(($record->student?->full_name ?? 'Unknown Student').' — '.($record->status ?? '—')))
                                    ->searchable()
                                    ->preload(),
                                Select::make('student_id')
                                    ->label('Student')
                                    ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('status')
                                    ->options(StudentSectionManualCompletion::STATUS_OPTIONS)
                                    ->default(StudentSectionManualCompletion::STATUS_PENDING)
                                    ->required(),
                                DateTimePicker::make('approved_at')
                                    ->label('Approved at'),
                                Select::make('approver_user_id')
                                    ->label('Approver')
                                    ->relationship('approverUser', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('evidence_reference')
                                    ->maxLength(255),
                            ]))),
                        Textarea::make('completion_summary')
                            ->rows(6)
                            ->columnSpanFull(),
                        Textarea::make('approver_notes')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

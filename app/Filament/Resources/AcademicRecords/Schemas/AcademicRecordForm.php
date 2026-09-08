<?php

namespace App\Filament\Resources\AcademicRecords\Schemas;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\GradeScale;
use App\Models\GradeValue;
use App\Models\Institution;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AcademicRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        $existingRecord = fn (?AcademicRecord $record): bool => $record !== null;

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
                                    ->required()
                                    ->disabled($existingRecord),
                                Select::make('student_id')
                                    ->label('Student')
                                    ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled($existingRecord),
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'title', fn ($query) => $query->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled($existingRecord),
                                Select::make('academic_term_id')
                                    ->label('Academic term')
                                    ->relationship('academicTerm', 'name', fn ($query) => $query->orderedForSelection())
                                    ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => $record->display_label)
                                    ->searchable()
                                    ->preload()
                                    ->disabled($existingRecord),
                                Select::make('course_enrollment_id')
                                    ->label('Course enrollment')
                                    ->relationship('courseEnrollment', 'uuid', fn ($query) => $query->latest('id'))
                                    ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): string => "{$record->student->full_name} — {$record->course->title}")
                                    ->searchable()
                                    ->preload()
                                    ->disabled($existingRecord),
                                TextInput::make('course_code')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled($existingRecord),
                                TextInput::make('course_title')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled($existingRecord),
                                TextInput::make('credits_attempted')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->disabled($existingRecord),
                                TextInput::make('credits_earned')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->disabled($existingRecord),
                                TextInput::make('final_grade')
                                    ->label('Final grade')
                                    ->maxLength(20)
                                    ->disabled($existingRecord),
                                TextInput::make('grade_points')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->disabled($existingRecord),
                                Select::make('grade_scale_id')
                                    ->label('Grade scale')
                                    ->options(function (Get $get): array {
                                        $institutionId = $get('institution_id');

                                        if (blank($institutionId)) {
                                            return [];
                                        }

                                        return GradeScale::query()
                                            ->where('institution_id', $institutionId)
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->disabled($existingRecord),
                                Select::make('grade_value_id')
                                    ->label('Grade value')
                                    ->options(function (Get $get): array {
                                        $institutionId = $get('institution_id');
                                        $gradeScaleId = $get('grade_scale_id');

                                        if (blank($institutionId) || blank($gradeScaleId)) {
                                            return [];
                                        }

                                        return GradeValue::query()
                                            ->where('institution_id', $institutionId)
                                            ->where('grade_scale_id', $gradeScaleId)
                                            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                                            ->orderBy('sort_order')
                                            ->orderBy('grade')
                                            ->get()
                                            ->mapWithKeys(fn (GradeValue $gradeValue) => [
                                                $gradeValue->id => $gradeValue->label
                                                    ? "{$gradeValue->grade} — {$gradeValue->label}"
                                                    : $gradeValue->grade,
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->disabled($existingRecord),
                                TextInput::make('grade_label')
                                    ->maxLength(255)
                                    ->disabled($existingRecord),
                                Toggle::make('earns_credit')->disabled($existingRecord),
                                Toggle::make('affects_gpa')->disabled($existingRecord),
                                Toggle::make('is_passing')->disabled($existingRecord),
                                Select::make('status')
                                    ->options(AcademicRecord::statusOptions())
                                    ->default('in_progress')
                                    ->required()
                                    ->disabled($existingRecord),
                                DatePicker::make('completed_at')
                                    ->label('Completed date')
                                    ->disabled($existingRecord),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

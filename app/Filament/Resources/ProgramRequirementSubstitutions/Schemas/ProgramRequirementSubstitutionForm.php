<?php

namespace App\Filament\Resources\ProgramRequirementSubstitutions\Schemas;

use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementSubstitution;
use App\Models\Student;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramRequirementSubstitutionForm
{
    public static function configure(Schema $schema, ?Student $ownerStudent = null): Schema
    {
        return $schema
            ->components([
                Section::make('Requirement Substitution Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                $ownerStudent === null
                                    ? Select::make('institution_id')
                                        ->label('Institution')
                                        ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                        ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                    : Hidden::make('institution_id')
                                        ->default($ownerStudent->institution_id)
                                        ->required(),
                                $ownerStudent === null
                                    ? Select::make('student_id')
                                        ->label('Student')
                                        ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                        ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                    : Hidden::make('student_id')
                                        ->default($ownerStudent->id)
                                        ->required(),
                                $ownerStudent === null
                                    ? Select::make('program_id')
                                        ->label('Program')
                                        ->relationship('program', 'title', fn ($query) => $query->orderBy('title'))
                                        ->getOptionLabelFromRecordUsing(fn (Program $record): string => $record->title)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                    : Hidden::make('program_id')
                                        ->default($ownerStudent->program_id)
                                        ->required(),
                                Select::make('program_requirement_id')
                                    ->label('Requirement')
                                    ->options(function (Get $get) use ($ownerStudent): array {
                                        $programId = $ownerStudent?->program_id ?? $get('program_id');

                                        if (blank($programId)) {
                                            return [];
                                        }

                                        return ProgramRequirement::query()
                                            ->where('program_id', $programId)
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('substitute_course_id')
                                    ->label('Substitute course')
                                    ->relationship('substituteCourse', 'title', fn ($query) => $query->orderBy('code')->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                                    ->searchable()
                                    ->preload()
                                    ->requiredWithout('academic_record_id'),
                                Select::make('academic_record_id')
                                    ->label('Academic record')
                                    ->options(function (Get $get) use ($ownerStudent): array {
                                        $studentId = $ownerStudent?->id ?? $get('student_id');

                                        if (blank($studentId)) {
                                            return [];
                                        }

                                        return AcademicRecord::query()
                                            ->where('student_id', $studentId)
                                            ->orderByDesc('completed_at')
                                            ->orderBy('course_code')
                                            ->get()
                                            ->mapWithKeys(fn (AcademicRecord $record) => [
                                                $record->id => trim("{$record->course_code} — {$record->course_title} ({$record->status})"),
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->requiredWithout('substitute_course_id'),
                                Select::make('status')
                                    ->options(ProgramRequirementSubstitution::STATUS_OPTIONS)
                                    ->default('pending')
                                    ->required(),
                                DatePicker::make('approved_at')
                                    ->label('Approved date'),
                                Select::make('approved_by_user_id')
                                    ->label('Approved by')
                                    ->relationship('approvedByUser', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                                    ->searchable()
                                    ->preload(),
                            ]),
                        Textarea::make('reason')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

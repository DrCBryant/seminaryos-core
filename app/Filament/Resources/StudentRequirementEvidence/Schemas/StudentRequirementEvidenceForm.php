<?php

namespace App\Filament\Resources\StudentRequirementEvidence\Schemas;

use App\Models\Institution;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\StudentRequirementEvidence;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentRequirementEvidenceForm
{
    public static function configure(Schema $schema, ?Student $ownerStudent = null): Schema
    {
        return $schema
            ->components([
                Section::make('Requirement Evidence Details')
                    ->schema([
                        Grid::make(2)
                            ->schema(array_values(array_filter([
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
                                            ->get()
                                            ->mapWithKeys(fn (ProgramRequirement $requirement) => [
                                                $requirement->id => $requirement->name,
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('status')
                                    ->options(StudentRequirementEvidence::STATUS_OPTIONS)
                                    ->default('pending')
                                    ->required(),
                                TextInput::make('evidence_title')
                                    ->maxLength(255),
                                DatePicker::make('completed_at')
                                    ->label('Completed date'),
                                DatePicker::make('approved_at')
                                    ->label('Approved date'),
                                Select::make('approved_by_user_id')
                                    ->label('Approved by')
                                    ->relationship('approvedByUser', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                                    ->searchable()
                                    ->preload(),
                            ]))),
                        Textarea::make('evidence_description')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

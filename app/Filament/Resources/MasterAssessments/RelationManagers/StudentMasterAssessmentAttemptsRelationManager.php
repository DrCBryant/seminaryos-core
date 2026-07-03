<?php

namespace App\Filament\Resources\MasterAssessments\RelationManagers;

use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentMasterAssessmentAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentMasterAssessmentAttempts';

    protected static ?string $title = 'Student Attempts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student_id')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('courseEnrollment.id')
                    ->label('Enrollment')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StudentMasterAssessmentAttempt::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('assessed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('assessorUser.name')
                    ->label('Assessor')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        $data['institution_id'] = $ownerRecord->institution_id;
                        $data['master_assessment_id'] = $ownerRecord->id;
                        $data['course_offering_id'] = $ownerRecord->course_offering_id;

                        return $data;
                    })
                    ->form([
                        Hidden::make('institution_id')->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
                        Hidden::make('master_assessment_id')->default(fn (): ?int => $this->getOwnerRecord()->id),
                        Hidden::make('course_offering_id')->default(fn (): ?int => $this->getOwnerRecord()->course_offering_id),
                        Select::make('student_id')
                            ->label('Student')
                            ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                            ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('course_enrollment_id')
                            ->label('Course enrollment')
                            ->relationship('courseEnrollment', 'id', fn ($query) => $query->orderByDesc('id'))
                            ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): string => trim("#{$record->id} — {$record->student?->full_name} — {$record->status}"))
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->options(StudentMasterAssessmentAttempt::STATUS_OPTIONS)
                            ->default(StudentMasterAssessmentAttempt::STATUS_NOT_STARTED)
                            ->required(),
                        DateTimePicker::make('submitted_at'),
                        DateTimePicker::make('assessed_at'),
                        Select::make('assessor_user_id')
                            ->label('Assessor')
                            ->relationship('assessorUser', 'name', fn ($query) => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                            ->searchable()
                            ->preload(),
                        Textarea::make('assessor_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

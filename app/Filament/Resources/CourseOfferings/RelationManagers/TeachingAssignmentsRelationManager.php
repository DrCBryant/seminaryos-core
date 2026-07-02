<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Filament\Resources\TeachingAssignments\Schemas\TeachingAssignmentForm;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\Institution;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    protected static ?string $title = 'Teaching Assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('faculty.full_name')
            ->columns([
                TextColumn::make('faculty.full_name')
                    ->label('Faculty')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TeachingAssignmentForm::ROLE_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TeachingAssignmentForm::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('assigned_at')
                    ->label('Assigned at')
                    ->date()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Ended at')
                    ->date()
                    ->sortable(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        $data['institution_id'] = $ownerRecord->institution_id;
                        $data['course_offering_id'] = $ownerRecord->id;
                        $data['course_id'] = $ownerRecord->course_id;
                        $data['academic_term_id'] = $ownerRecord->academic_term_id;

                        return $data;
                    })
                    ->form([
                        Hidden::make('institution_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
                        Hidden::make('course_offering_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->id),
                        Hidden::make('course_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->course_id),
                        Hidden::make('academic_term_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->academic_term_id),
                        Select::make('faculty_id')
                            ->label('Faculty')
                            ->relationship('faculty', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                            ->getOptionLabelFromRecordUsing(fn (Faculty $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('role')
                            ->options(TeachingAssignmentForm::ROLE_OPTIONS)
                            ->required(),
                        Select::make('status')
                            ->options(TeachingAssignmentForm::STATUS_OPTIONS)
                            ->default('assigned')
                            ->required(),
                        DatePicker::make('assigned_at')
                            ->label('Assigned at'),
                        DatePicker::make('ended_at')
                            ->label('Ended at')
                            ->afterOrEqual('assigned_at'),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Select::make('institution_id')
                            ->label('Institution')
                            ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('faculty_id')
                            ->label('Faculty')
                            ->relationship('faculty', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                            ->getOptionLabelFromRecordUsing(fn (Faculty $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('course_offering_id')
                            ->label('Course offering')
                            ->relationship('courseOffering', 'section_code', fn ($query) => $query->orderByDesc('academic_term_id')->orderBy('section_code'))
                            ->getOptionLabelFromRecordUsing(fn (CourseOffering $record): string => trim("{$record->course?->code} — {$record->academicTerm?->name} ({$record->academicTerm?->academic_year}) — {$record->section_code}"))
                            ->searchable()
                            ->preload(),
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
                        Select::make('role')
                            ->options(TeachingAssignmentForm::ROLE_OPTIONS)
                            ->required(),
                        Select::make('status')
                            ->options(TeachingAssignmentForm::STATUS_OPTIONS)
                            ->required(),
                        DatePicker::make('assigned_at')
                            ->label('Assigned at'),
                        DatePicker::make('ended_at')
                            ->label('Ended at')
                            ->afterOrEqual('assigned_at'),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

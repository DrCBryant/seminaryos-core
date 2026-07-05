<?php

namespace App\Filament\Resources\StudentSectionSubmissions\Tables;

use App\Models\CourseOffering;
use App\Models\SectionAssignment;
use App\Models\Student;
use App\Models\StudentSectionSubmission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentSectionSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name', 'student_number'])
                    ->sortable(),
                TextColumn::make('courseOffering.section_code')
                    ->label('Course offering')
                    ->state(fn (StudentSectionSubmission $record): string => trim(($record->courseOffering?->course?->code ?? '—').' — '.($record->courseOffering?->academicTerm?->name ?? '—').' — '.($record->courseOffering?->section_code ?? '—')))
                    ->sortable(),
                TextColumn::make('sectionAssignment.title')
                    ->label('Section assignment')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StudentSectionSubmission::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewerUser.name')
                    ->label('Reviewer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('score')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                IconColumn::make('passed')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('course_offering_id')
                    ->label('Course offering')
                    ->options(fn (): array => CourseOffering::query()
                        ->orderByDesc('academic_term_id')
                        ->orderBy('section_code')
                        ->get()
                        ->mapWithKeys(fn (CourseOffering $record) => [
                            $record->id => trim(($record->course?->code ?? '—').' — '.($record->academicTerm?->name ?? '—').' — '.($record->section_code ?? '—')),
                        ])
                        ->all())
                    ->searchable(),
                SelectFilter::make('section_assignment_id')
                    ->label('Section assignment')
                    ->options(fn (): array => SectionAssignment::query()
                        ->orderBy('sort_order')
                        ->orderBy('due_at')
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('student_id')
                    ->label('Student')
                    ->options(fn (): array => Student::query()
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Student $record) => [$record->id => $record->full_name])
                        ->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(StudentSectionSubmission::STATUS_OPTIONS),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

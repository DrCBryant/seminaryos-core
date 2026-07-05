<?php

namespace App\Filament\Resources\StudentSectionManualCompletions\Tables;

use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\StudentSectionManualCompletion;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentSectionManualCompletionsTable
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
                    ->state(fn (StudentSectionManualCompletion $record): string => trim(($record->courseOffering?->course?->code ?? '—').' — '.($record->courseOffering?->academicTerm?->name ?? '—').' — '.($record->courseOffering?->section_code ?? '—')))
                    ->sortable(),
                TextColumn::make('courseEnrollment.status')
                    ->label('Enrollment status')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StudentSectionManualCompletion::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Approved at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approverUser.name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
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
                    ->options(StudentSectionManualCompletion::STATUS_OPTIONS),
                SelectFilter::make('approver_user_id')
                    ->label('Approver')
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
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

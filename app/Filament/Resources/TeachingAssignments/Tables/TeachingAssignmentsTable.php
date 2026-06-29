<?php

namespace App\Filament\Resources\TeachingAssignments\Tables;

use App\Filament\Resources\TeachingAssignments\Schemas\TeachingAssignmentForm;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Faculty;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TeachingAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('faculty.full_name')
                    ->label('Faculty')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('course.title')
                    ->label('Course')
                    ->description(fn ($record): string => $record->course?->code ?? '')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicTerm.name')
                    ->label('Academic term')
                    ->searchable()
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
                    ->label('Assigned date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Academic term')
                    ->options(fn () => AcademicTerm::query()
                        ->orderByDesc('academic_year')
                        ->orderBy('start_date')
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => "{$term->name} ({$term->academic_year})"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('faculty_id')
                    ->label('Faculty')
                    ->options(fn () => Faculty::query()
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Faculty $faculty) => [$faculty->id => $faculty->full_name])
                        ->all())
                    ->searchable(),
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::query()
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [$course->id => "{$course->code} — {$course->title}"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('role')
                    ->options(TeachingAssignmentForm::ROLE_OPTIONS),
                SelectFilter::make('status')
                    ->options(TeachingAssignmentForm::STATUS_OPTIONS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

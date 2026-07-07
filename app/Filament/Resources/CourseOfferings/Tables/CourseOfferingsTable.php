<?php

namespace App\Filament\Resources\CourseOfferings\Tables;

use App\Filament\Resources\CourseOfferings\CourseOfferingResource;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseOfferingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.title')
                    ->label('Course')
                    ->formatStateUsing(fn (?string $state, $record): string => "{$record->course->code} — {$record->course->title}")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicTerm.name')
                    ->label('Academic term')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section_code')
                    ->label('Section code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Unlimited' : (string) $state)
                    ->sortable(),
                TextColumn::make('enrolled_count')
                    ->label('Enrolled')
                    ->state(fn (CourseOffering $record): int => $record->enrolled_count ?? $record->enrolledCount())
                    ->sortable(),
                TextColumn::make('available_seats')
                    ->label('Available seats')
                    ->state(fn (CourseOffering $record): string|int => $record->capacity === null
                        ? 'Unlimited'
                        : max(($record->capacity ?? 0) - ($record->enrolled_count ?? $record->enrolledCount()), 0))
                    ->badge()
                    ->color(fn (CourseOffering $record): string => $record->capacityStatus() === CourseOffering::CAPACITY_STATUS_FULL ? 'danger' : 'gray'),
                TextColumn::make('capacity_status')
                    ->label('Capacity status')
                    ->state(fn (CourseOffering $record): string => $record->capacityStatus())
                    ->badge()
                    ->color(fn (CourseOffering $record): string => match ($record->capacityStatus()) {
                        CourseOffering::CAPACITY_STATUS_FULL => 'danger',
                        CourseOffering::CAPACITY_STATUS_NEARLY_FULL => 'warning',
                        default => 'success',
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('enrolled_count', $direction)),
                TextColumn::make('courseEnrollments_count')
                    ->label('Enrollments')
                    ->counts('courseEnrollments')
                    ->sortable(),
                TextColumn::make('teachingAssignments_count')
                    ->label('Teaching assignments')
                    ->counts('teachingAssignments')
                    ->sortable(),
                TextColumn::make('sectionAssignments_count')
                    ->label('Assignments')
                    ->counts('sectionAssignments')
                    ->sortable(),
                TextColumn::make('delivery_mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseOffering::DELIVERY_MODE_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseOffering::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('progress_basis')
                    ->label('Progress basis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseOffering::PROGRESS_BASIS_OPTIONS[$state] ?? $state)
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCapacityAwareness())
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Academic term')
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderedForSelection()
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => $term->display_label])
                        ->all())
                    ->searchable(),
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn (): array => Course::query()
                        ->orderBy('code')
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [$course->id => "{$course->code} — {$course->title}"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('delivery_mode')
                    ->options(CourseOffering::DELIVERY_MODE_OPTIONS),
                SelectFilter::make('status')
                    ->options(CourseOffering::STATUS_OPTIONS),
                SelectFilter::make('progress_basis')
                    ->label('Progress basis')
                    ->options(CourseOffering::PROGRESS_BASIS_OPTIONS),
            ])
            ->recordActions([
                CourseOfferingResource::generateManualCompletionChecklistAction(),
                CourseOfferingResource::generateSubmissionChecklistAction(),
                CourseOfferingResource::sectionProgressAction(),
                CourseOfferingResource::completionReviewAction(),
                CourseOfferingResource::rosterAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

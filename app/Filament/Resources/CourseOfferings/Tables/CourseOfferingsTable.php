<?php

namespace App\Filament\Resources\CourseOfferings\Tables;

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
                TextColumn::make('delivery_mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseOffering::DELIVERY_MODE_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseOffering::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Academic term')
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('academic_year')
                        ->orderBy('start_date')
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => "{$term->name} ({$term->academic_year})"])
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

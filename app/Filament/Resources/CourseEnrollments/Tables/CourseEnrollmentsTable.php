<?php

namespace App\Filament\Resources\CourseEnrollments\Tables;

use App\Models\Course;
use App\Models\AcademicTerm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CourseEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicTerm.name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('final_grade')
                    ->label('Final grade')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('enrolled_at')
                    ->label('Enrolled date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn () => AcademicTerm::query()
                        ->orderByDesc('academic_year')
                        ->orderBy('start_date')
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => "{$term->name} ({$term->academic_year})"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options([
                        'enrolled' => 'Enrolled',
                        'dropped' => 'Dropped',
                        'withdrawn' => 'Withdrawn',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'incomplete' => 'Incomplete',
                    ]),
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::query()
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [$course->id => "{$course->code} — {$course->title}"])
                        ->all())
                    ->searchable(),
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

<?php

namespace App\Filament\Resources\AcademicRecords\Tables;

use App\Filament\Resources\AcademicRecords\Schemas\AcademicRecordForm;
use App\Models\AcademicTerm;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AcademicRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('course_code')
                    ->label('Course code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_title')
                    ->label('Course title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicTerm.name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('final_grade')
                    ->label('Final grade')
                    ->sortable(),
                TextColumn::make('credits_earned')
                    ->label('Credits earned')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AcademicRecordForm::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('Completed date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('student_id')
                    ->label('Student')
                    ->options(fn () => Student::query()
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Student $student) => [$student->id => $student->full_name])
                        ->all())
                    ->searchable(),
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
                    ->options(AcademicRecordForm::STATUS_OPTIONS),
                SelectFilter::make('final_grade')
                    ->label('Final grade')
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                        'D' => 'D',
                        'F' => 'F',
                        'P' => 'P',
                        'W' => 'W',
                        'I' => 'I',
                    ]),
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

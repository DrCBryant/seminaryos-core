<?php

namespace App\Filament\Resources\MasterAssessments\Tables;

use App\Models\CourseOffering;
use App\Models\MasterAssessment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MasterAssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('courseOffering.section_code')
                    ->label('Course offering')
                    ->formatStateUsing(fn (?string $state, MasterAssessment $record): string => trim("{$record->courseOffering?->course?->code} — {$record->courseOffering?->academicTerm?->name} — {$record->courseOffering?->section_code}"))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('passing_threshold')
                    ->label('Passing threshold')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MasterAssessment::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('course_offering_id')
                    ->label('Course offering')
                    ->options(fn (): array => CourseOffering::query()
                        ->with(['course', 'academicTerm'])
                        ->orderByDesc('academic_term_id')
                        ->orderBy('section_code')
                        ->get()
                        ->mapWithKeys(fn (CourseOffering $offering) => [
                            $offering->id => trim("{$offering->course?->code} — {$offering->academicTerm?->name} — {$offering->section_code}"),
                        ])
                        ->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(MasterAssessment::STATUS_OPTIONS),
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

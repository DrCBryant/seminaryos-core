<?php

namespace App\Filament\Resources\AcademicTerms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AcademicTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academic_year')
                    ->label('Academic year')
                    ->sortable(),
                TextColumn::make('term_type')
                    ->label('Term type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Start date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('End date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_year')
                    ->options(fn () => \App\Models\AcademicTerm::query()
                        ->orderByDesc('academic_year')
                        ->pluck('academic_year', 'academic_year')
                        ->all()),
                SelectFilter::make('term_type')
                    ->options([
                        'fall' => 'Fall',
                        'spring' => 'Spring',
                        'summer' => 'Summer',
                        'winter' => 'Winter',
                        'intensive' => 'Intensive',
                        'custom' => 'Custom',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
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

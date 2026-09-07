<?php

namespace App\Filament\Resources\Faculty\Tables;

use App\Models\Faculty;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FacultyTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Faculty')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('teachingAssignments_count')
                    ->label('Assignments')
                    ->counts('teachingAssignments')
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Faculty::statusOptions())
                    ->multiple(),
                SelectFilter::make('is_public')
                    ->label('Public visibility')
                    ->options([
                        '1' => 'Public',
                        '0' => 'Private',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

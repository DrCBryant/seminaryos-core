<?php

namespace App\Filament\Resources\Courses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Course code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Course title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credit_hours')
                    ->label('Credits')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('programs.title')
                    ->label('Related programs')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('institution')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('is_public')
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

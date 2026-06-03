<?php

namespace App\Filament\Resources\Programs\Tables;

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

class ProgramsTable
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
                    ->label('Program code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Program name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credential_type')
                    ->label('Degree level')
                    ->toggleable(),
                TextColumn::make('courses_count')
                    ->label('Courses')
                    ->counts('courses')
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
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
